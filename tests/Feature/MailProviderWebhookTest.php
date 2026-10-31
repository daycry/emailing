<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MailMessage;
use App\Models\Suppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderWebhookTest extends TestCase
{
    use MailProviderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withServerVariables(['HTTPS' => 'on']);
        Http::preventStrayRequests();
    }

    private function message($account, string $provider, array $values = []): MailMessage
    {
        $sender = $this->providerSender($account, ['email' => Str::uuid().'@example.test']);

        return MailMessage::create(array_replace(['account_id' => $account->id, 'mail_sender_id' => $sender->id,
            'mode' => $provider.'_api', 'status' => 'api_accepted', 'origin' => 'test', 'sender_email' => $sender->email,
            'recipient' => 'recipient@example.test', 'subject' => 'Fixture', 'provider_reference' => (string) Str::uuid(),
            'transport_started_at' => now()], $values));
    }

    private function brevoEvent(MailMessage $message, string $kind, int $offset = 0): array
    {
        return ['event' => $kind, 'id' => 123, 'email' => $message->recipient, 'ts_event' => time() + $offset,
            'message-id' => 'synthetic-receipt', 'X-Mailin-custom' => 'emailing_reference:'.$message->provider_reference];
    }

    private function brevo($connection, array $event)
    {
        return $this->withHeader('Authorization', 'Bearer '.$connection->webhook_secret)
            ->postJson('https://emailing.example.test/webhooks/mail/brevo/'.$connection->id, $event);
    }

    private function sendgrid($connection, array $events, $private, ?int $timestamp = null, bool $alter = false)
    {
        $body = json_encode($events, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $timestamp = (string) ($timestamp ?? time());
        $this->assertTrue(openssl_sign($timestamp.$body, $signature, $private, OPENSSL_ALGO_SHA256));

        return $this->call('POST', 'https://emailing.example.test/webhooks/mail/sendgrid/'.$connection->id, [], [], [], [
            'HTTPS' => 'on', 'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($signature),
        ], $body.($alter ? ' ' : ''));
    }

    public function test_authenticated_brevo_unknown_reference_is_consumed_without_effects(): void
    {
        [, $account] = $this->providerOwner();
        $connection = $this->providerConnection($account, 'brevo');
        $this->withServerVariables(['HTTPS' => 'on'])->withHeader('Authorization', 'Bearer '.$connection->webhook_secret)
            ->postJson('https://emailing.example.test/webhooks/mail/brevo/'.$connection->id, ['event' => 'delivered', 'email' => 'recipient@example.test',
                'ts_event' => time(), 'X-Mailin-custom' => 'emailing_reference:00000000-0000-4000-8000-000000000001'])
            ->assertNoContent();
        $this->assertDatabaseCount('suppressions', 0);
    }

    public function test_brevo_replays_reordering_and_repeated_webhook_id_preserve_acceptance_and_suppressions(): void
    {
        [$user, $account] = $this->providerOwner();
        $connection = $this->providerConnection($account, 'brevo');
        $message = $this->message($account, 'brevo', ['actor_id' => $user->id]);
        $this->brevo($connection, $this->brevoEvent($message, 'soft_bounce', -20))->assertNoContent();
        $this->assertDatabaseCount('suppressions', 0);
        $hard = $this->brevoEvent($message, 'hard_bounce', -10);
        $this->brevo($connection, $hard)->assertNoContent();
        $this->brevo($connection, $hard)->assertNoContent();
        $this->brevo($connection, $this->brevoEvent($message, 'delivered'))->assertNoContent();
        $this->assertDatabaseCount('mail_provider_events', 3);
        $this->assertDatabaseHas('suppressions', ['account_id' => $account->id, 'source' => 'provider_bounce']);
        $this->assertSame('bounced', $message->refresh()->provider_status);
        $this->assertSame('api_accepted', $message->status);
        $this->assertSame($user->id, $message->actor_id);
        $this->assertSame(3, AuditEvent::where('action', 'mail.provider_feedback')->count());
    }

    public function test_brevo_authentication_rotation_bounds_and_tenant_correlation_are_enforced(): void
    {
        [, $account] = $this->providerOwner();
        [, $other] = $this->providerOwner();
        $connection = $this->providerConnection($account, 'brevo');
        $foreign = $this->message($other, 'brevo');
        $wrongProvider = $this->message($account, 'sendgrid');
        $message = $this->message($account, 'brevo');
        $this->withHeader('Authorization', 'Bearer incorrect')->postJson('https://emailing.example.test/webhooks/mail/brevo/'.$connection->id, [])->assertUnauthorized();
        foreach ([$foreign, $wrongProvider] as $wrong) {
            $this->brevo($connection, $this->brevoEvent($wrong, 'spam'))->assertNoContent();
        }
        $this->brevo($connection, array_replace($this->brevoEvent($message, 'spam'), ['email' => 'different@example.test']))->assertNoContent();
        $old = $connection->webhook_secret;
        $connection->update(['webhook_secret' => str_repeat('rotated-', 8)]);
        $this->withHeader('Authorization', 'Bearer '.$old)->postJson('https://emailing.example.test/webhooks/mail/brevo/'.$connection->id, $this->brevoEvent($message, 'spam'))->assertUnauthorized();
        $this->brevo($connection, $this->brevoEvent($message, 'spam'))->assertNoContent();
        $this->assertDatabaseCount('mail_provider_events', 1);
        $this->assertDatabaseCount('suppressions', 1);
        $connection->update(['enabled' => false]);
        $this->brevo($connection, $this->brevoEvent($message, 'delivered'))->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$connection->webhook_secret)->postJson('http://emailing.example.test/webhooks/mail/brevo/'.$connection->id, [])->assertUnauthorized();
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->call('POST', 'https://emailing.example.test/webhooks/mail/brevo/'.$connection->id, [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('x', 1048577))->assertStatus(413);
    }

    public function test_sendgrid_verifies_original_bytes_timestamp_and_key_before_any_effect(): void
    {
        [, $account] = $this->providerOwner();
        $key = $this->providerSigningKey();
        $otherKey = $this->providerSigningKey();
        $connection = $this->providerConnection($account, 'sendgrid', ['webhook_public_key' => $key['public']]);
        $message = $this->message($account, 'sendgrid', ['status' => 'sending']);
        $event = ['event' => 'delivered', 'email' => $message->recipient, 'timestamp' => time(),
            'sg_event_id' => 'synthetic-event-1', 'emailing_reference' => $message->provider_reference];
        $this->sendgrid($connection, [$event], $otherKey['private'])->assertUnauthorized();
        $this->sendgrid($connection, [$event], $key['private'], alter: true)->assertUnauthorized();
        $this->sendgrid($connection, [$event], $key['private'], time() - 86401)->assertUnauthorized();
        $this->sendgrid($connection, [$event], $key['private'], time() + 301)->assertUnauthorized();
        $this->assertDatabaseCount('mail_provider_events', 0);
        $this->sendgrid($connection, [$event], $key['private'])->assertNoContent();
        $this->sendgrid($connection, [$event], $key['private'])->assertNoContent();
        $this->assertSame('delivered', $message->refresh()->provider_status);
        $this->assertSame('sending', $message->status); // Callback can precede the transport response.
        $this->assertDatabaseCount('mail_provider_events', 1);
        $this->sendgrid($connection, [], $key['private'])->assertStatus(422);
        $this->sendgrid($connection, array_fill(0, 1001, $event), $key['private'])->assertStatus(422);
    }

    public function test_sendgrid_batch_is_bounded_and_all_permanent_suppression_kinds_are_idempotent(): void
    {
        [, $account] = $this->providerOwner();
        $key = $this->providerSigningKey();
        $connection = $this->providerConnection($account, 'sendgrid', ['webhook_public_key' => $key['public']]);
        $message = $this->message($account, 'sendgrid');
        $events = [];
        for ($i = 0; $i < 1000; $i++) {
            $events[] = ['event' => ['processed', 'deferred', 'bounce', 'spamreport', 'unsubscribe'][$i % 5],
                'email' => $message->recipient, 'timestamp' => time(), 'sg_event_id' => 'synthetic-event-'.$i,
                'emailing_reference' => $message->provider_reference];
        }
        DB::enableQueryLog();
        $this->sendgrid($connection, $events, $key['private'])->assertNoContent();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        $this->assertLessThanOrEqual(16, $queries, 'Batch processing must not issue per-event SQL.');
        $this->assertDatabaseCount('mail_provider_events', 1000);
        $this->assertDatabaseCount('suppressions', 1);
        $this->sendgrid($connection, $events, $key['private'])->assertNoContent();
        $this->assertDatabaseCount('mail_provider_events', 1000);
        $this->assertSame('complained', $message->refresh()->provider_status);
        $this->assertSame('api_accepted', $message->status);
        $this->assertNotNull(Suppression::where('account_id', $account->id)->sole());
    }
}
