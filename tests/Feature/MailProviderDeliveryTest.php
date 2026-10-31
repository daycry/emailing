<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSetting;
use App\Models\Suppression;
use App\Services\AccountKeyService;
use App\Services\AccountMailTransport;
use App\Services\ActivityService;
use App\Services\CampaignService;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryMaterializer;
use App\Services\DeliveryObserver;
use App\Services\MailProviders\ProviderWebhookService;
use App\Services\Mcp\ContractArguments;
use App\Services\SendingApiService;
use App\Services\SmtpConnector;
use App\Support\SmtpPolicyViolation;
use App\Support\SmtpRejected;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\MailProviderFixtures;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class MailProviderDeliveryTest extends TestCase
{
    use DatabaseMigrations, MailProviderFixtures, McpFixtures;

    private array $ownedAccounts = [];

    protected function tearDown(): void
    {
        DB::table('api_mail_requests')->whereIn('account_id', $this->ownedAccounts)->delete();
        parent::tearDown();
    }

    private function setupProvider(string $mode = 'brevo_api'): array
    {
        [$user, $account] = $this->providerOwner();
        $this->ownedAccounts[] = $account->id;
        $sender = $this->providerSender($account, ['lab_authorized' => false]);
        $brevo = $this->providerConnection($account, 'brevo');
        $sendgrid = $this->providerConnection($account, 'sendgrid');
        MailSetting::create(['account_id' => $account->id, 'mode' => $mode]);
        config(['emailing.external_enabled' => true, 'app.url' => 'https://emailing.example.test',
            'delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000]);
        Http::preventStrayRequests();
        $key = app(AccountKeyService::class)->ui('api-keys.write', fn ($account, $ceiling) => app(AccountKeyService::class)->create(
            $account, ['name' => 'Provider fixture', 'scopes' => ['mail.send', 'activity.read']], $ceiling, actor: $user->id));

        return [$user, $account, $sender, $key, $brevo, $sendgrid];
    }

    private function accept(array $key, $sender, string $idempotency): array
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$key['secret'], 'Idempotency-Key' => $idempotency])
            ->postJson('/api/v1/mail/send', $this->payload($sender))->assertStatus(202)->json();
    }

    private function payload($sender): array
    {
        return ['sender_id' => $sender->id, 'recipients' => [['email' => 'recipient@example.test']],
            'content' => ['type' => 'inline', 'subject' => 'Provider fixture', 'text' => 'Synthetic content']];
    }

    private function claim(string $delivery): ?Delivery
    {
        $event = DeliveryOutbox::where('delivery_id', $delivery)->where('type', 'delivery.ready')->orderByDesc('generation')->firstOrFail();

        return app(DeliveryLifecycle::class)->claim($event->payload());
    }

    private function rpc(string $bearer, string $name, array $arguments = []): array
    {
        $params = ['name' => $name, 'arguments' => (object) $arguments,
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]];
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => $params], [
            'Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => $name])->assertOk();
        $this->assertFalse($response->json('result.isError'));
        $dto = $response->json('result.structuredContent');
        $this->assertTrue(ContractArguments::valid(json_decode(json_encode($dto, JSON_THROW_ON_ERROR)), ContractArguments::operation($name)['outputSchema']));

        return $dto;
    }

    public function test_rest_accepts_an_external_only_sender_for_a_prepared_provider(): void
    {
        [$user, $account, $sender, $key] = $this->setupProvider();
        $this->mcpConfiguration();
        [, $grant, $bearer] = $this->mcpCredential($user, $account);
        $calls = 0;
        $this->mock(SmtpConnector::class)->shouldReceive('send')->andReturnUsing(function () use (&$calls): void {
            $calls++;
        });
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'synthetic-receipt'], 201),
            'api.sendgrid.com/*' => Http::response('', 202, ['X-Message-Id' => 'synthetic-receipt'])]);
        $contact = Contact::create(['account_id' => $account->id, 'email' => 'recipient@example.test', 'name' => 'Synthetic',
            'consent_status' => 'granted', 'consent_source' => 'Fixture', 'consented_at' => now()->subMinute()]);
        $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'Provider fixture', 'subject' => 'Fixture', 'text' => 'Synthetic', 'html' => '']);
        foreach (['brevo_api', 'sendgrid_api', 'brevo_smtp', 'sendgrid_smtp'] as $mode) {
            MailSetting::where('account_id', $account->id)->update(['mode' => $mode]);
            $expected = str_ends_with($mode, '_api') ? 'api_accepted' : 'smtp_accepted';
            $receipt = $this->accept($key, $sender, 'synthetic-rest-'.$mode);
            $replayed = $this->accept($key, $sender, 'synthetic-rest-'.$mode);
            $this->assertEquals($receipt, $replayed); // MySQL JSON objects do not preserve member order.
            $this->assertSame($receipt['delivery_ids'], $replayed['delivery_ids']);
            $mcp = $this->rpc($bearer, 'mail_send', ['idempotency_key' => 'synthetic-mcp-'.$mode, 'message' => $this->payload($sender)])['data']['request'];
            $service = app(CampaignService::class);
            $campaign = $service->save($account, $user->id, null, null, ['name' => $mode, 'template_id' => $template->id,
                'sender_id' => $sender->id, 'contact_ids' => [$contact->id], 'list_ids' => []]);
            $campaign = $service->review($account, $user->id, $campaign->id, $campaign->version);
            $campaign = $service->authorizeCampaign($account, $user->id, $campaign->id, $campaign->version, $campaign->reviewed_revision_id, true);
            $this->assertSame(1, app(DeliveryMaterializer::class)->materialize($account->id, $campaign->id));
            $campaignDelivery = Delivery::where('campaign_id', $campaign->id)->sole();
            foreach ([$receipt['delivery_ids'][0], $mcp['delivery_ids'][0], $campaignDelivery->id] as $id) {
                $claim = $this->claim($id);
                $this->assertNotNull($claim);
                $record = app(AccountMailTransport::class)->sendDelivery($claim);
                $this->assertSame($expected, $record->status);
                $this->assertSame($expected, Delivery::findOrFail($id)->status);
                $this->assertNull($this->claim($id));
                $this->assertTrue(Str::isUuid($record->provider_reference));
            }
            $status = app(SendingApiService::class)->status($account->id, $receipt['request_id']);
            $this->assertSame(1, $status['counts'][$expected]);
            $this->assertSame(0, $status['counts'][$expected === 'api_accepted' ? 'smtp_accepted' : 'api_accepted']);
            $this->assertSame($expected, $this->rpc($bearer, 'mail_request_get', ['request_id' => $mcp['request_id']])['data']['request']['deliveries'][0]['status']);
            $senderDto = $this->rpc($bearer, 'connection_info', ['senders' => (object) []])['data']['senders']['items'][0];
            $this->assertFalse($senderDto['lab_authorized']);
            $this->assertTrue($senderDto['external_authorized']);
        }
        $this->assertSame(6, $calls);
        Http::assertSentCount(6);
        $metrics = app(ActivityService::class)->metrics([], $account->id);
        foreach (['api', 'campaign', 'mcp'] as $origin) {
            $this->assertSame(2, $metrics[$origin]['api_accepted']);
            $this->assertSame(2, $metrics[$origin]['smtp_accepted']);
        }
    }

    public function test_rate_limit_has_one_safe_requeue_but_permanent_or_uncertain_results_do_not_retry(): void
    {
        [, $account, $sender, $key] = $this->setupProvider();
        config(['delivery.max_attempts' => 20]);
        Http::fake(['api.brevo.com/*' => Http::sequence()->push('', 429, ['Retry-After' => '120'])->push('', 429)->push('', 401)->push('', 503)]);
        $receipt = $this->accept($key, $sender, 'synthetic-rate-limit-1');
        $id = $receipt['delivery_ids'][0];
        $message = app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('api_rejected', $message->status);
        $delivery = Delivery::findOrFail($id);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(2, $delivery->generation);
        $this->assertTrue($delivery->available_at->greaterThan(now()->addSeconds(110)));
        Delivery::whereKey($id)->update(['available_at' => now()->subSecond()]);
        app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('failed', $delivery->refresh()->status);
        $this->assertSame(2, $delivery->attempts);
        foreach (['permanent' => 'failed', 'uncertain' => 'ambiguous'] as $scenario => $expected) {
            $receipt = $this->accept($key, $sender, 'synthetic-'.$scenario.'-1');
            $id = $receipt['delivery_ids'][0];
            app(AccountMailTransport::class)->sendDelivery($this->claim($id));
            $this->assertSame($expected, Delivery::findOrFail($id)->status);
            $this->assertNull($this->claim($id));
        }
        Http::assertSentCount(4);
    }

    public function test_feedback_received_after_requeue_blocks_the_next_durable_start(): void
    {
        [, $account, $sender, $key, $connection] = $this->setupProvider();
        Http::fake(['api.brevo.com/*' => Http::sequence()->push('', 429)->push(['messageId' => 'synthetic-second'], 201)]);
        $receipt = $this->accept($key, $sender, 'synthetic-late-feedback');
        $id = $receipt['delivery_ids'][0];
        $message = app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('pending', Delivery::findOrFail($id)->status);
        app(ProviderWebhookService::class)->consume('brevo', $connection->id, json_encode(['event' => 'delivered',
            'email' => $message->recipient, 'ts_event' => time(), 'X-Mailin-custom' => 'emailing_reference:'.$message->provider_reference], JSON_THROW_ON_ERROR),
            ['authorization' => 'Bearer '.$connection->webhook_secret]);
        Delivery::whereKey($id)->update(['available_at' => now()->subSecond()]);
        $claim = $this->claim($id);
        $this->assertNotNull($claim);
        $this->assertNull(app(AccountMailTransport::class)->sendDelivery($claim));
        $delivery = Delivery::findOrFail($id);
        $this->assertSame('ambiguous', $delivery->status);
        $this->assertSame('provider_feedback_conflict', $delivery->diagnostic);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNull($this->claim($id));
        Http::assertSentCount(1);
    }

    public function test_feedback_for_a_prior_attempt_during_retry_prevents_any_further_requeue(): void
    {
        [, $account, $sender, $key, $connection] = $this->setupProvider('brevo_smtp');
        $attempt = 0;
        $this->mock(SmtpConnector::class)->shouldReceive('send')->twice()->andReturnUsing(function () use (&$attempt, $account, $connection): void {
            if (++$attempt === 2) {
                $first = MailMessage::where('account_id', $account->id)->orderBy('id')->firstOrFail();
                $this->assertSame(0, DB::transactionLevel());
                app(ProviderWebhookService::class)->consume('brevo', $connection->id, json_encode(['event' => 'delivered',
                    'email' => $first->recipient, 'ts_event' => time(), 'X-Mailin-custom' => 'emailing_reference:'.$first->provider_reference], JSON_THROW_ON_ERROR),
                    ['authorization' => 'Bearer '.$connection->webhook_secret]);
            }
            throw new SmtpRejected('session', 454);
        });
        $receipt = $this->accept($key, $sender, 'synthetic-prior-attempt-feedback');
        $id = $receipt['delivery_ids'][0];
        app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('pending', Delivery::findOrFail($id)->status);
        Delivery::whereKey($id)->update(['available_at' => now()->subSecond()]);
        $message = app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('smtp_unconfirmed', $message->status);
        $this->assertSame('provider_feedback_conflict', $message->diagnostic);
        $delivery = Delivery::findOrFail($id);
        $this->assertSame('ambiguous', $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertNull($this->claim($id));
    }

    public function test_early_authenticated_feedback_prevents_retry_and_is_projected_without_changing_acceptance(): void
    {
        [, $account, $sender, $key, $connection] = $this->setupProvider();
        Http::fake(['api.brevo.com/*' => function () use ($account, $connection) {
            $message = MailMessage::where('account_id', $account->id)->sole();
            $this->assertSame(0, DB::transactionLevel(), 'Provider IO must happen outside SQL locks.');
            app(ProviderWebhookService::class)->consume('brevo', $connection->id, json_encode(['event' => 'hard_bounce',
                'email' => $message->recipient, 'ts_event' => time(), 'X-Mailin-custom' => 'emailing_reference:'.$message->provider_reference], JSON_THROW_ON_ERROR),
                ['authorization' => 'Bearer '.$connection->webhook_secret]);

            return Http::response('', 429);
        }]);
        $receipt = $this->accept($key, $sender, 'synthetic-early-feedback');
        $id = $receipt['delivery_ids'][0];
        app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $delivery = Delivery::findOrFail($id);
        $this->assertSame('ambiguous', $delivery->status);
        $this->assertSame('provider_feedback_conflict', $delivery->diagnostic);
        $status = app(SendingApiService::class)->status($account->id, $receipt['request_id']);
        $this->assertSame('provider_events', $status['delivery_feedback']);
        $this->assertSame('bounced', $status['deliveries'][0]['provider_feedback']['status']);
        $this->assertDatabaseHas('suppressions', ['account_id' => $account->id, 'source' => 'provider_bounce']);
        $this->assertNull($this->claim($id));
        Http::assertSentCount(1);
    }

    public function test_configuration_is_frozen_and_live_suppression_authorization_and_fencing_stop_stale_work(): void
    {
        [$user, $account, $sender, $key, $connection] = $this->setupProvider();
        Http::fake(['api.brevo.com/*' => function ($request) use ($account, $connection) {
            $this->assertSame(0, DB::transactionLevel());
            $connection->update(['api_key' => 'synthetic-rotated-key']);
            MailSetting::where('account_id', $account->id)->update(['mode' => 'sendgrid_api']);
            $this->assertTrue($request->hasHeader('api-key', 'synthetic-brevo-api-secret'));

            return Http::response(['messageId' => 'synthetic-frozen-receipt'], 201);
        }]);
        $receipt = $this->accept($key, $sender, 'synthetic-frozen-config');
        $message = app(AccountMailTransport::class)->sendDelivery($this->claim($receipt['delivery_ids'][0]));
        $this->assertSame('brevo_api', $message->mode);
        $this->assertSame('api_accepted', $message->status);
        MailSetting::where('account_id', $account->id)->update(['mode' => 'brevo_api']);
        foreach (['suppression', 'sender', 'profile', 'lease'] as $scenario) {
            $receipt = $this->accept($key, $sender, 'synthetic-live-'.$scenario);
            $claim = $this->claim($receipt['delivery_ids'][0]);
            if ($scenario === 'suppression') {
                Suppression::create(['account_id' => $account->id, 'normalized_email' => 'recipient@example.test', 'source' => 'manual']);
            } elseif ($scenario === 'sender') {
                $sender->update(['external_authorized' => false]);
            } elseif ($scenario === 'profile') {
                $connection->update(['enabled' => false]);
            } else {
                Delivery::whereKey($claim->id)->update(['lease_expires_at' => now()->subMinute()]);
            }
            $this->assertNull(app(AccountMailTransport::class)->sendDelivery($claim));
            Suppression::where('account_id', $account->id)->delete();
            $sender->update(['external_authorized' => true]);
            $connection->update(['enabled' => true]);
        }
        Http::assertSentCount(1);
    }

    public function test_recovery_wins_over_a_late_api_acceptance_without_repeating_io(): void
    {
        [, $account, $sender, $key] = $this->setupProvider();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'synthetic-late-receipt'], 201)]);
        $this->mock(DeliveryObserver::class)->shouldReceive('observe')->andReturnUsing(function ($stage, Delivery $claim): void {
            if ($stage === 'transport_result') {
                Delivery::whereKey($claim->id)->update(['lease_expires_at' => now()->subMinute()]);
                $this->assertSame(1, app(DeliveryLifecycle::class)->recover());
            }
        });
        $receipt = $this->accept($key, $sender, 'synthetic-late-api-result');
        $id = $receipt['delivery_ids'][0];
        app(AccountMailTransport::class)->sendDelivery($this->claim($id));
        $this->assertSame('ambiguous', Delivery::findOrFail($id)->status);
        $message = MailMessage::where('account_id', $account->id)->sole();
        $this->assertSame('api_unconfirmed', $message->status);
        $this->assertSame('sending_lease_expired', $message->diagnostic);
        $this->assertNull($message->provider_message_id);
        $this->assertNull($this->claim($id));
        Http::assertSentCount(1);
    }

    public function test_external_smtp_permanent_authentication_and_tls_policy_rejections_do_not_requeue(): void
    {
        [, $account, $sender, $key] = $this->setupProvider('brevo_smtp');
        $failures = [new SmtpRejected('session', 535), new SmtpPolicyViolation];
        $this->mock(SmtpConnector::class)->shouldReceive('send')->twice()->andReturnUsing(function () use (&$failures): void {
            throw array_shift($failures);
        });
        foreach (['auth', 'tls'] as $reason) {
            $receipt = $this->accept($key, $sender, 'synthetic-smtp-failure-'.$reason);
            $id = $receipt['delivery_ids'][0];
            $message = app(AccountMailTransport::class)->sendDelivery($this->claim($id));
            $this->assertSame('smtp_rejected', $message->status);
            $this->assertSame($reason === 'auth' ? 'smtp_negative_session' : 'smtp_tls_required', $message->diagnostic);
            $delivery = Delivery::findOrFail($id);
            $this->assertSame('failed', $delivery->status);
            $this->assertSame(1, $delivery->attempts);
            $this->assertSame(1, $delivery->generation);
            $this->assertSame(1, DeliveryOutbox::where('delivery_id', $id)->where('type', 'delivery.ready')->count());
            $this->assertNull($this->claim($id));
        }
        Http::assertNothingSent();
    }
}
