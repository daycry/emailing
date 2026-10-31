<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MailMessage;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderWebhookRaceTest extends TestCase
{
    use DatabaseMigrations, MailProviderFixtures;

    public function test_concurrent_authenticated_replay_commits_one_event_and_one_suppression(): void
    {
        [, $account] = $this->providerOwner();
        $connection = $this->providerConnection($account, 'brevo');
        $sender = $this->providerSender($account);
        $message = MailMessage::create(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'mode' => 'brevo_api',
            'status' => 'api_accepted', 'origin' => 'test', 'sender_email' => $sender->email, 'recipient' => 'recipient@example.test',
            'subject' => 'Concurrent fixture', 'provider_reference' => (string) Str::uuid(), 'transport_started_at' => now()]);
        $directory = base_path('tests/.runtime/provider-webhook-'.Str::uuid());
        $this->assertTrue(mkdir($directory, 0700, true));
        $file = $directory.'/event.private.json';
        file_put_contents($file, json_encode(['connection' => $connection->id, 'authorization' => 'Bearer '.$connection->webhook_secret,
            'body' => json_encode(['event' => 'hard_bounce', 'email' => $message->recipient, 'ts_event' => time(),
                'X-Mailin-custom' => 'emailing_reference:'.$message->provider_reference], JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR));
        $children = [];
        $success = false;
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', base_path('tests/support/mail-provider-webhook-race.php'), $file],
                    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                $children[] = ['process' => $process, 'pipes' => $pipes];
            }
            foreach ($children as $child) {
                fwrite($child['pipes'][0], "GATE\n");
                fclose($child['pipes'][0]);
            }
            $deadline = microtime(true) + 40;
            foreach ($children as $child) {
                do {
                    $state = proc_get_status($child['process']);
                    if (! $state['running']) {
                        break;
                    }
                    usleep(10000);
                } while (microtime(true) < $deadline);
                $this->assertFalse($state['running'], 'Owned webhook child exceeded its budget.');
                $this->assertSame(0, $state['exitcode']);
                $this->assertSame('ok', stream_get_contents($child['pipes'][1]));
            }
            $this->assertDatabaseCount('mail_provider_events', 1);
            $this->assertDatabaseCount('suppressions', 1);
            $this->assertSame('bounced', $message->refresh()->provider_status);
            $this->assertSame('api_accepted', $message->status);
            $this->assertSame(1, AuditEvent::where('action', 'mail.provider_feedback')->count());
            $success = true;
        } finally {
            foreach ($children as $child) {
                if (proc_get_status($child['process'])['running']) {
                    proc_terminate($child['process']);
                }
                foreach ($child['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($child['process']);
            }
            if ($success && str_starts_with(realpath($directory), realpath(base_path('tests/.runtime')).DIRECTORY_SEPARATOR)) {
                unlink($file);
                rmdir($directory);
            }
        }
    }
}
