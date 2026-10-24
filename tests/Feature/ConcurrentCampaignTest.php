<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\TestCase;

#[Group('delivery')]
class ConcurrentCampaignTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCampaigns;

    private function parallel(array $actions): array
    {
        $directory = storage_path('app/private/campaign-concurrency-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = [];
        try {
            foreach ($actions as $i => $action) {
                $process = new Process([PHP_BINARY, base_path('tests/support/campaign-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode($action + ['account_id' => $this->account->id, 'actor_id' => $this->owner->id, 'ready' => $directory.'/ready-'.$i, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = count(glob($directory.'/ready-*')) === count($processes);
                if (microtime(true) > $deadline) {
                    $this->fail('Los procesos concurrentes no llegaron a la barrera: '.implode(' ', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
                }
                usleep(10000);
            } while (! $ready);
            file_put_contents($directory.'/start', 'start');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }

            return array_map(fn ($p) => trim($p->getOutput()), $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_two_mysql_processes_authorize_exactly_one_reviewed_version(): void
    {
        $campaign = $this->reviewed();
        $action = ['action' => 'authorize', 'id' => $campaign->id, 'version' => $campaign->version, 'revision_id' => $campaign->reviewed_revision_id];
        $results = $this->parallel([$action, $action]);
        sort($results);
        $this->assertSame(['authorized', 'rejected:409'], $results);
        $this->assertSame($campaign->version + 1, $campaign->fresh()->version);
        $this->assertSame(1, AuditEvent::where('action', 'campaign.authorized')->count());
        $this->assertDatabaseCount('campaign_recipients', 1);
    }

    public function test_two_mysql_processes_edit_and_authorize_cannot_commit_an_unseen_composition(): void
    {
        $campaign = $this->reviewed();
        $base = ['id' => $campaign->id, 'version' => $campaign->version, 'revision_id' => $campaign->reviewed_revision_id];
        $results = $this->parallel([['action' => 'authorize'] + $base, ['action' => 'save', 'input' => $this->input(['name' => 'Concurrente'])] + $base]);
        $this->assertSame(1, count(array_filter($results, fn ($value) => $value === 'rejected:409')));
        $this->assertSame($campaign->version + 1, $campaign->fresh()->version);
        if ($campaign->fresh()->status === 'authorized') {
            $this->assertSame('Noticias', $campaign->fresh()->name);
            $this->assertSame(1, AuditEvent::where('action', 'campaign.authorized')->count());
        } else {
            $this->assertSame('draft', $campaign->fresh()->status);
            $this->assertSame('Concurrente', $campaign->fresh()->name);
            $this->assertNull($campaign->fresh()->reviewed_revision_id);
            $this->assertSame(0, AuditEvent::where('action', 'campaign.authorized')->count());
        }
    }

    public function test_two_mysql_processes_pause_only_once_and_old_resume_version_is_rejected(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $action = ['action' => 'pause', 'id' => $campaign->id, 'version' => $campaign->version];
        $results = $this->parallel([$action, $action]);
        sort($results);
        $this->assertSame(['paused', 'rejected:409'], $results);
        $this->assertRejected(409, fn () => $this->service->resume($this->account, $this->owner->id, $campaign->id, $campaign->version));
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertSame(0, $this->service->due($this->account)->count());
    }

    public function test_windows_delete_pending_cleanup_waits_for_the_owned_handle_to_close(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('The delete-pending regression exercises the documented native Windows harness.');
        }
        $directory = storage_path('app/private/campaign-cleanup-'.Str::uuid());
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/pending', 'Controlled fixture');
        $handle = fopen($directory.'/pending', 'r');
        $this->assertIsResource($handle);
        $attempts = [];
        try {
            $this->cleanOwnedCampaignDirectory($directory, function (bool $removed) use (&$handle, &$attempts): void {
                $attempts[] = $removed;
                if (is_resource($handle)) {
                    fclose($handle);
                }
            });
            $this->assertFalse($attempts[0]);
            $this->assertTrue(end($attempts));
            $this->assertGreaterThanOrEqual(2, count($attempts));
            $this->assertDirectoryDoesNotExist($directory);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_dir($directory)) {
                $this->cleanOwnedCampaignDirectory($directory);
            }
        }
    }

    private function cleanOwnedCampaignDirectory(string $directory, ?callable $observe = null): void
    {
        $parent = realpath(storage_path('app/private'));
        if (is_link($directory) || realpath(dirname($directory)) !== $parent || ! preg_match('/^campaign-(cleanup|revocation)-[0-9a-f-]{36}$/D', basename($directory))) {
            throw new \RuntimeException('Only an attributed campaign fixture directory may be removed.');
        }
        $deadline = hrtime(true) + 2_000_000_000;
        do {
            foreach (array_diff(scandir($directory), ['.', '..']) as $entry) {
                $file = $directory.'/'.$entry;
                if (is_dir($file)) {
                    throw new \RuntimeException('An unexpected nested campaign fixture directory must not be removed.');
                }
                @unlink($file);
            }
            $removed = @rmdir($directory);
            if ($observe !== null) {
                $observe($removed);
            }
            if ($removed) {
                return;
            }
            usleep(10000);
            clearstatcache(true, $directory);
        } while (hrtime(true) < $deadline);
        throw new \RuntimeException('Owned campaign fixture cleanup did not finish.');
    }

    public function test_authorization_waiting_on_mysql_account_mutex_observes_committed_scope_revocation(): void
    {
        $campaign = $this->reviewed();
        $member = $this->member(['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read', 'mail.send']);
        $identity = (string) Str::uuid();
        $directory = storage_path('app/private/campaign-revocation-'.$identity);
        $evidence = (getenv('T13_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t13/implementation')).'/campaign-revocation-'.$identity;
        mkdir($directory, 0700, true);
        mkdir($evidence, 0700, true);
        $record = static function (string $phase, array $facts) use ($evidence): void {
            file_put_contents($evidence.'/'.$phase.'.json', json_encode(['phase' => $phase, 'monotonic_ns' => hrtime(true), 'at_utc' => gmdate('c')] + $facts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        };
        $process = new Process([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', base_path('tests/support/campaign-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '', 'XDEBUG_MODE' => 'off']);
        $input = ['action' => 'authorize', 'account_id' => $this->account->id, 'actor_id' => $member->user_id, 'id' => $campaign->id, 'version' => $campaign->version, 'revision_id' => $campaign->reviewed_revision_id, 'loaded' => $directory.'/loaded', 'ready' => $directory.'/ready', 'start' => $directory.'/start', 'attempting' => $directory.'/attempting'];
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(30);
        $record('request', ['command' => $process->getCommandLine(), 'input' => $input, 'helper_sha256' => hash_file('sha256', base_path('tests/support/campaign-action.php'))]);
        $primary = null;
        $wrapperPid = null;
        $started = false;
        try {
            DB::beginTransaction();
            Account::whereKey($this->account->id)->lockForUpdate()->firstOrFail();
            $record('parent_mutex', ['connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, 'account_id' => $this->account->id]);
            $process->start();
            $started = true;
            $wrapperPid = $process->getPid();
            $record('process_started', ['wrapper_pid' => $wrapperPid]);
            // Preserve the existing shared 15-second ready/attempt budget.
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/ready')) {
                if (microtime(true) > $deadline || ! $process->isRunning()) {
                    $this->fail('The owned campaign process did not reach readiness.');
                }
                usleep(10000);
            }
            $ready = json_decode(file_get_contents($directory.'/ready'), true, flags: JSON_THROW_ON_ERROR);
            $record('ready_observed', $ready);
            $this->assertSame(hash_file('sha256', base_path('tests/support/campaign-action.php')), $ready['helper_sha256']);
            $this->assertGreaterThan(0, $ready['connection_id']);
            file_put_contents($directory.'/start', 'start');
            while (! is_file($directory.'/attempting')) {
                if (microtime(true) > $deadline || ! $process->isRunning()) {
                    $this->fail('The owned campaign process did not attempt authorization.');
                }
                usleep(10000);
            }
            $blocked = false;
            do {
                foreach (DB::select('SHOW FULL PROCESSLIST') as $thread) {
                    if ((int) $thread->Id !== $ready['connection_id']) {
                        continue;
                    }
                    // Inspect only this owned thread; never persist SQL text.
                    $sql = strtolower(str_replace('`', '', (string) $thread->Info));
                    $blocked = $thread->db === 'emailing_testing'
                        && in_array($thread->Command, ['Query', 'Execute'], true)
                        && preg_match('/\bselect\b[\s\S]*\bfrom\s+(?:emailing_testing\.)?accounts\b[\s\S]*\bfor\s+update\b/', $sql) === 1;
                }
                if ($blocked) {
                    break;
                }
                if (microtime(true) > $deadline || ! $process->isRunning()) {
                    $this->fail('The owned authorization query was not observed waiting on the account mutex.');
                }
                usleep(10000);
            } while (true);
            $this->assertTrue($blocked);
            $record('sql_wait_observed', ['connection_id' => $ready['connection_id'], 'executor_pid' => $ready['pid'], 'account_for_update' => true, 'parent_mutex_held' => DB::transactionLevel() > 0]);
            $member->update(['scopes' => ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']]);
            DB::commit();
            $record('parent_commit', ['scopes_revoked' => true]);
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('rejected:403', trim($process->getOutput()));
            $this->assertSame('reviewed', $campaign->fresh()->status);
            $this->assertNull($campaign->fresh()->authorized_at);
            $this->assertSame(0, AuditEvent::where('action', 'campaign.authorized')->count());
            $record('assertions_complete', ['rejected_403' => true, 'campaign_reviewed' => true, 'authorization_audit_count' => 0]);
        } catch (\Throwable $error) {
            $primary = $error;
            throw $error;
        } finally {
            $secondary = [];
            $settle = static function (string $phase, callable $operation) use (&$secondary): void {
                try {
                    $operation();
                } catch (\Throwable $error) {
                    $secondary[] = ['phase' => $phase, 'class' => $error::class, 'code' => $error->getCode()];
                }
            };
            $settle('rollback', static function (): void {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            });
            $settle('stop', function () use ($process, $started, $record, $wrapperPid): void {
                if ($started && $process->isRunning()) {
                    $record('stop_request', ['wrapper_pid' => $wrapperPid]);
                    $process->stop();
                }
            });
            $settle('process_receipt', static function () use ($record, $process, $wrapperPid, $started, $evidence): void {
                if ($started) {
                    file_put_contents($evidence.'/stdout.txt', $process->getOutput());
                    file_put_contents($evidence.'/stderr.txt', $process->getErrorOutput());
                }
                $record('process_receipt', ['wrapper_pid' => $wrapperPid, 'wrapper_exit' => $started ? $process->getExitCode() : null, 'executor_exit' => null, 'status' => $process->getStatus()]);
            });
            $settle('markers', static function () use ($directory, $evidence): void {
                foreach (['loaded', 'ready', 'start', 'attempting'] as $name) {
                    if (is_file($directory.'/'.$name)) {
                        copy($directory.'/'.$name, $evidence.'/'.$name.'-marker.json');
                    }
                }
            });
            $settle('directory_cleanup', function () use ($directory, $record): void {
                if (is_dir($directory)) {
                    $attempt = 0;
                    $this->cleanOwnedCampaignDirectory($directory, static function (bool $removed) use ($record, &$attempt): void {
                        $record('cleanup_attempt_'.++$attempt, ['directory_removed' => $removed]);
                    });
                }
            });
            $settle('settlement_receipt', static function () use ($record, $primary, &$secondary, $directory): void {
                $record('settlement', ['primary' => $primary === null ? null : ['class' => $primary::class, 'code' => $primary->getCode(), 'line' => $primary->getLine()], 'secondary_errors' => $secondary, 'directory_removed' => ! is_dir($directory)]);
            });
            if ($primary === null && $secondary !== []) {
                throw new \RuntimeException('Owned campaign fixture settlement failed; separate evidence preserves its phases.');
            }
        }
    }
}
