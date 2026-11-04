<?php

namespace Tests\Feature;

use App\Support\TestingDatabaseGuard;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ReplaysMigrationHistory;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

class MigrationReplayTest extends TestCase
{
    use DatabaseMigrations;
    use ReplaysMigrationHistory;

    private const TARGET = '2026_10_25_000005_create_mcp_sessions';

    private const WINDOW = [
        self::TARGET,
        '2026_10_25_000006_create_mcp_audit_events_table',
        '2026_10_26_000007_share_transactional_mail_origins',
        '2026_10_30_000001_create_mail_provider_connections',
        '2026_10_30_000002_add_mail_provider_authorization_to_senders',
        '2026_10_30_000003_add_mail_provider_receipts_to_messages',
        '2026_10_30_000004_create_mail_provider_events',
    ];

    public function test_each_progress_snapshot_is_new_and_the_final_trace_retains_the_complete_window(): void
    {
        $paths = [];
        $snapshots = [];
        try {
            $this->replayMigrationWindow(self::TARGET, function (): void {
                $this->assertFalse(Schema::hasTable('mcp_sessions'));
                $this->assertFalse(Schema::hasTable('mcp_audit_events'));
            }, function (string $path, array $trace) use (&$paths, &$snapshots): void {
                $paths[] = $path;
                $this->assertFileDoesNotExist($path, 'Progress must not replace an existing snapshot.');
                OutboxProtocolBarrier::publish($path, $trace);
                $snapshots[] = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            });
            $steps = 2 * count(self::WINDOW);
            $this->assertCount($steps + 1, $snapshots);
            $this->assertCount($steps + 1, array_unique($paths));
            $this->assertSame([...range(1, $steps), $steps], array_map(fn ($trace) => count($trace['steps']), $snapshots));
            $final = $snapshots[$steps];
            $this->assertSame([...array_fill(0, count(self::WINDOW), 'down'), ...array_fill(0, count(self::WINDOW), 'up')], array_column($final['steps'], 'direction'));
            $this->assertSame([...array_reverse(self::WINDOW), ...self::WINDOW], array_column($final['steps'], 'migration'));
            $this->assertSame(self::WINDOW, $final['applied_window']);
            $this->assertTrue($final['restored']);
            $this->assertNull($final['primary_failure_class']);
            $this->assertSchemaRestored();
        } finally {
            foreach (array_unique($paths) as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public static function evidenceFailureStages(): array
    {
        return ['after-down' => ['down'], 'after-up' => ['up'], 'final' => ['final']];
    }

    #[DataProvider('evidenceFailureStages')]
    public function test_a_failed_trace_publication_fails_the_test_after_restoring_successful_ddl(string $stage): void
    {
        $failure = new \RuntimeException('Controlled evidence failure.');
        $fired = false;
        $snapshots = [];
        try {
            $this->replayMigrationWindow(self::TARGET, writeTrace: function (string $path, array $trace) use ($failure, $stage, &$fired, &$snapshots): void {
                $snapshots[] = $trace;
                $current = array_key_exists('restored', $trace) ? 'final' : end($trace['steps'])['direction'];
                if (! $fired && $current === $stage) {
                    $fired = true;
                    throw $failure;
                }
            });
            $this->fail('A trace failure must not become a passing test.');
        } catch (\RuntimeException $error) {
            $this->assertSame($failure, $error);
        }
        $this->assertTrue($fired);
        $this->assertSchemaRestored();
        $final = end($snapshots);
        $this->assertTrue($final['restored']);
        $this->assertCount(2 * count(self::WINDOW), $final['steps']);
        $this->assertSame(array_fill(0, 2 * count(self::WINDOW), true), array_column($final['steps'], 'completed'));
    }

    public function test_evidence_failure_cannot_mask_the_historical_assertion_error_or_stop_restoration(): void
    {
        $primary = new \LogicException('Controlled historical assertion failure.');
        $fired = false;
        $snapshots = [];
        try {
            $this->replayMigrationWindow(self::TARGET, static function () use ($primary): void {
                throw $primary;
            }, function (string $path, array $trace) use (&$fired, &$snapshots): void {
                $snapshots[] = $trace;
                if (! $fired && end($trace['steps'])['direction'] === 'up') {
                    $fired = true;
                    throw new \RuntimeException('Controlled restore evidence failure.');
                }
            });
            $this->fail('The original assertion error must be retained.');
        } catch (\LogicException $error) {
            $this->assertSame($primary, $error);
        }
        $this->assertTrue($fired);
        $this->assertSchemaRestored();
        $final = end($snapshots);
        $this->assertTrue($final['restored']);
        $this->assertSame(\LogicException::class, $final['primary_failure_class']);
        $this->assertSame(\RuntimeException::class, $final['evidence_failure_class']);
    }

    public function test_a_real_restoration_ddl_failure_stops_dependent_migrations_and_is_reported(): void
    {
        $snapshots = [];
        $conflictCreated = false;
        try {
            try {
                $this->replayMigrationWindow(self::TARGET, static function () use (&$conflictCreated): void {
                    Schema::create('mcp_sessions', static fn (Blueprint $table) => $table->id());
                    $conflictCreated = true;
                }, static function (string $path, array $trace) use (&$snapshots): void {
                    $snapshots[] = $trace;
                });
                $this->fail('Conflicting real DDL must fail restoration.');
            } catch (QueryException $error) {
                $this->assertSame('42S01', $error->errorInfo[0]);
            }
            $this->assertFalse(Schema::hasTable('mcp_audit_events'), 'A dependent migration must not run after prerequisite failure.');
            $this->assertFalse(Schema::hasColumn('api_mail_requests', 'origin'), 'Shared source DDL must also remain unapplied.');
            $final = end($snapshots);
            $this->assertFalse($final['restored']);
            $this->assertSame(QueryException::class, $final['restoration_failure_class']);
            $this->assertSame(QueryException::class, $final['primary_failure_class']);
        } finally {
            if ($conflictCreated) {
                // The deliberate test-owned conflict is repaired after preserving
                // its failed trace, so DatabaseMigrations can tear down the schema.
                TestingDatabaseGuard::assertSafe();
                Schema::dropIfExists('mcp_sessions');
                $trace = end($snapshots);
                $restored = array_column(array_filter($trace['steps'], static fn ($step) => $step['direction'] === 'up' && $step['completed']), 'migration');
                foreach (self::WINDOW as $name) {
                    if ($name === self::TARGET || ! in_array($name, $restored, true)) {
                        $migration = require database_path('migrations/'.$name.'.php');
                        $migration->up();
                    }
                }
                $this->assertSchemaRestored();
            }
        }
    }

    private function assertSchemaRestored(): void
    {
        $this->assertTrue(Schema::hasTable('mcp_sessions'), 'Successful session DDL must be restored.');
        $this->assertTrue(Schema::hasTable('mcp_audit_events'), 'Trace publication must not stop audit DDL restoration.');
        $this->assertTrue(Schema::hasColumn('api_mail_requests', 'origin'));
        $this->assertTrue(Schema::hasColumn('mail_messages', 'mcp_grant_id'));
        $this->assertTrue(Schema::hasTable('mail_provider_connections'));
        $this->assertTrue(Schema::hasColumn('mail_senders', 'external_authorized'));
        $this->assertTrue(Schema::hasColumn('mail_messages', 'provider_reference'));
        $this->assertTrue(Schema::hasIndex('mail_messages', 'mail_messages_provider_reference_unique', 'unique'));
        $this->assertTrue(Schema::hasIndex('mail_messages', 'mail_messages_account_id_id_unique', 'unique'));
        $this->assertTrue(Schema::hasTable('mail_provider_events'));
    }
}
