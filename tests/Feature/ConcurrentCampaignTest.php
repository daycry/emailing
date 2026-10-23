<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\TestCase;

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

    public function test_authorization_waiting_on_mysql_account_mutex_observes_committed_scope_revocation(): void
    {
        $campaign = $this->reviewed();
        $member = $this->member(['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read', 'mail.send']);
        $directory = storage_path('app/private/campaign-revocation-'.Str::uuid());
        mkdir($directory, 0700, true);
        $process = new Process([PHP_BINARY, base_path('tests/support/campaign-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
        $process->setInput(json_encode(['action' => 'authorize', 'account_id' => $this->account->id, 'actor_id' => $member->user_id, 'id' => $campaign->id, 'version' => $campaign->version, 'revision_id' => $campaign->reviewed_revision_id, 'ready' => $directory.'/ready', 'start' => $directory.'/start', 'attempting' => $directory.'/attempting'], JSON_THROW_ON_ERROR));
        $process->setTimeout(30);
        DB::beginTransaction();
        try {
            Account::whereKey($this->account->id)->lockForUpdate()->firstOrFail();
            $process->start();
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/ready')) {
                if (microtime(true) > $deadline) {
                    $this->fail('El proceso no llegó a la barrera: '.$process->getErrorOutput());
                }
                usleep(10000);
            }
            file_put_contents($directory.'/start', 'start');
            while (! is_file($directory.'/attempting')) {
                if (microtime(true) > $deadline) {
                    $this->fail('El proceso no intentó la autorización.');
                }
                usleep(10000);
            }
            $member->update(['scopes' => ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']]);
            // The child has attempted the service while this real MySQL lock remains held.
            usleep(150000);
            $this->assertTrue($process->isRunning());
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame('rejected:403', trim($process->getOutput()));
            $this->assertSame('reviewed', $campaign->fresh()->status);
            $this->assertNull($campaign->fresh()->authorized_at);
            $this->assertSame(0, AuditEvent::where('action', 'campaign.authorized')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop();
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
