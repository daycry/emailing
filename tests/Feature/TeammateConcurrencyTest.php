<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TeammateConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_acceptance_creates_one_membership_and_one_audit_event(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $account = Account::create(['name' => 'Concurrent test', 'owner_id' => $owner->id]);
        $token = Str::random(64);
        Invitation::create(['account_id' => $account->id, 'invited_by' => $owner->id, 'email' => $recipient->email, 'role' => 'read-only', 'scopes' => [], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
        $directory = storage_path('app/private/concurrency-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/support/accept-invitation.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing']);
                $process->setInput(json_encode(['user_id' => $recipient->id, 'token' => $token, 'ready' => $directory.'/ready-'.$i, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Child processes did not reach the concurrent barrier.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/start', 'start');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Concurrent child process failed.');
            }
            $results = array_map(fn ($process) => trim($process->getOutput()), $processes);
            sort($results);
            $this->assertSame(['accepted', 'rejected:410'], $results);
            $this->assertSame(1, Membership::where('account_id', $account->id)->where('user_id', $recipient->id)->count());
            $this->assertDatabaseCount('audit_events', 1);
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
}
