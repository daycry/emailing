<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountMailTransport;
use App\Services\SmtpConnector;
use App\Services\SuppressionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SuppressionStartTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(): array
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $account = Account::create(['name' => 'Equipo', 'owner_id' => $owner->id]);
        Membership::create(['account_id' => $account->id, 'user_id' => $owner->id, 'role' => 'admin', 'scopes' => []]);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Laboratorio', 'lab_authorized' => true]);

        return [$account, $owner, $sender];
    }

    private function child(Account $account, User $owner, MailSender $sender, string $action): array
    {
        $directory = storage_path('phase3-t10/concurrency-'.Str::uuid());
        mkdir($directory, 0700, true);
        $child = new Process([PHP_BINARY, base_path('tests/support/suppression-boundary.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
        $child->setInput(json_encode(['account_id' => $account->id, 'actor_id' => $owner->id, 'sender_id' => $sender->id, 'action' => $action, 'directory' => $directory], JSON_THROW_ON_ERROR));
        $child->setTimeout(20);
        $child->start();
        $this->awaitFile($directory.'/ready');

        return [$child, $directory];
    }

    private function awaitFile(string $path): void
    {
        $deadline = microtime(true) + 10;
        while (! is_file($path) && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertFileExists($path);
    }

    private function childResult(Process $child, string $directory): array
    {
        $child->wait();
        file_put_contents($directory.'/child.stdout.txt', $child->getOutput());
        file_put_contents($directory.'/child.stderr.txt', $child->getErrorOutput());
        $this->assertTrue($child->isSuccessful(), 'Child failed; inspect private boundary receipt.');

        return json_decode($child->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_committed_suppression_wins_while_another_mysql_process_waits_to_start(): void
    {
        [$account, $owner, $sender] = $this->fixture();
        [$child, $directory] = $this->child($account, $owner, $sender, 'send');
        try {
            DB::transaction(function () use ($account, $owner, $directory, $child) {
                Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
                file_put_contents($directory.'/start', 'start');
                $this->awaitFile($directory.'/attempting');
                usleep(250000);
                $this->assertTrue($child->isRunning(), 'Transport passed the locked account start boundary.');
                app(SuppressionService::class)->suppress($account, $owner->id, ['email' => 'recipient@example.test']);
            });
            $this->assertSame(['status' => 'suppressed', 'started' => false], $this->childResult($child, $directory));
            $this->assertFalse(is_dir($directory.'/artifacts'));
        } finally {
            if ($child->isRunning()) {
                $child->stop();
            }
        }
    }

    public function test_started_transport_has_no_sql_locks_and_a_later_suppression_blocks_the_next_start(): void
    {
        [$account, $owner, $sender] = $this->fixture();
        MailSetting::create(['account_id' => $account->id, 'mode' => 'mailpit']);
        [$child, $directory] = $this->child($account, $owner, $sender, 'suppress');
        try {
            $this->mock(SmtpConnector::class)->shouldReceive('send')->once()->andReturnUsing(function () use ($directory, $child) {
                $this->assertSame(0, DB::transactionLevel(), 'SMTP must run after the SQL start commit.');
                $this->assertDatabaseHas('mail_messages', ['status' => 'sending']);
                file_put_contents($directory.'/start', 'start');
                $this->assertSame(['suppressed' => true], $this->childResult($child, $directory));
            });
            $transport = app(AccountMailTransport::class);
            $input = ['recipient' => 'recipient@example.test', 'subject' => 'Prueba', 'text' => 'Laboratorio'];
            $started = $transport->send($account, $sender->id, $input);
            $this->assertSame('smtp_accepted', $started->status);
            $this->assertNotNull($started->transport_started_at);
            $this->assertDatabaseCount('suppressions', 1);
            $pending = $transport->send($account, $sender->id, $input);
            $this->assertSame('suppressed', $pending->status);
            $this->assertNull($pending->transport_started_at);
        } finally {
            if ($child->isRunning()) {
                $child->stop();
            }
        }
    }
}
