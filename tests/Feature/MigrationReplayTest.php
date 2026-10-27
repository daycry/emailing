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

    private const WINDOW = [self::TARGET, '2026_10_25_000006_create_mcp_audit_events_table', '2026_10_26_000007_share_transactional_mail_origins'];

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
            $this->assertCount(7, $snapshots);
            $this->assertCount(7, array_unique($paths));
            $this->assertSame([1, 2, 3, 4, 5, 6, 6], array_map(fn ($trace) => count($trace['steps']), $snapshots));
            $this->assertSame(['down', 'down', 'down', 'up', 'up', 'up'], array_column($snapshots[6]['steps'], 'direction'));
            $this->assertSame(self::WINDOW, $snapshots[6]['applied_window']);
            $this->assertTrue($snapshots[6]['restored']);
            $this->assertNull($snapshots[6]['primary_failure_class']);
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
        $this->assertCount(6, $final['steps']);
        $this->assertSame([true, true, true, true, true, true], array_column($final['steps'], 'completed'));
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
                foreach (self::WINDOW as $name) {
                    if ($name === self::TARGET
                        || ($name === self::WINDOW[1] && ! Schema::hasTable('mcp_audit_events'))
                        || ($name === self::WINDOW[2] && ! Schema::hasColumn('api_mail_requests', 'origin'))) {
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
    }
}
