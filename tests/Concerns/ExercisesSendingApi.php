<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\ApiMailRecipient;
use App\Models\ApiMailRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\Suppression;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\AccountMailTransport;
use App\Services\CampaignService;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryMaterializer;
use App\Services\TeamService;
use App\Support\SendingApiInput;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OutboxProtocolBarrier;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

trait ExercisesSendingApi
{
    private array $otherAccounts = [];

    private array $apiProcesses = [];

    private ?string $apiDirectory = null;

    private function evidence(string $name, array $facts): void
    {
        if ($this->apiDirectory === null) {
            $this->apiDirectory = (getenv('T18_EVIDENCE_DIRECTORY') ?: getenv('T16_EVIDENCE_DIRECTORY') ?: storage_path('phase4-t18-implementation')).'/api-'.Str::uuid();
            mkdir($this->apiDirectory, 0700, true);
        }
        OutboxProtocolBarrier::publish($this->apiDirectory.'/'.$name.'.json', ['at_utc' => gmdate('c'), 'pid' => getmypid()] + $facts);
    }

    private function foreign(): array
    {
        $account = Account::create(['owner_id' => $this->owner->id, 'name' => 'Foreign fixture '.Str::uuid()]);
        $this->otherAccounts[] = $account->id;
        Membership::create(['account_id' => $account->id, 'user_id' => $this->owner->id, 'role' => 'admin', 'scopes' => []]);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'foreign@example.test', 'name' => 'Foreign fixture', 'lab_authorized' => true]);
        $key = DB::transaction(function () use ($account) {
            Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            return app(AccountKeyService::class)->create($account->id, ['name' => 'Foreign key', 'scopes' => SendingApiInput::SCOPES], SendingApiInput::SCOPES);
        });

        return [$account, $sender, $key];
    }

    private function processApiDelivery(string $id): ?MailMessage
    {
        $event = DeliveryOutbox::where('delivery_id', $id)->where('type', 'delivery.ready')->orderByDesc('generation')->firstOrFail();
        $claim = app(DeliveryLifecycle::class)->claim(['account_id' => $this->account->id, 'delivery_id' => $id, 'event_id' => $event->id, 'generation' => $event->generation]);
        $this->assertNotNull($claim);

        return app(AccountMailTransport::class)->sendDelivery($claim);
    }

    public function test_recipient_admission_and_live_policy_cover_known_unknown_historical_and_late_changes(): void
    {
        $base = ['account_id' => $this->account->id, 'email' => 'direct@example.test', 'name' => 'Fixture contact', 'consent_status' => 'granted', 'consent_source' => 'Synthetic fixture', 'consented_at' => now('UTC')->subDay()];
        $cases = [
            ['consent_status' => 'pending'], ['consent_status' => 'revoked'], ['consent_source' => ''], ['consented_at' => null], ['consented_at' => now('UTC')->addDay()], ['deleted_at' => now('UTC')],
        ];
        foreach ($cases as $change) {
            $contact = Contact::create(array_replace($base, $change));
            if (array_key_exists('deleted_at', $change)) {
                $contact->delete();
            }
            $this->api()->assertStatus(422);
            $this->assertSame(0, ApiMailRequest::count());
            $this->assertSame(0, Delivery::count());
            $this->assertSame(0, DeliveryOutbox::count());
            $contact->forceDelete();
        }
        $suppression = Suppression::create(['account_id' => $this->account->id, 'normalized_email' => $base['email'], 'source' => 'manual']);
        $this->api()->assertStatus(422);
        $suppression->delete();
        [$foreign] = $this->foreign();
        Contact::create(array_replace($base, ['account_id' => $foreign->id, 'consent_status' => 'revoked']));
        foreach (['direct', 'known', 'deleted', 'identity', 'revoked', 'hard-delete', 'late-contact', 'late-suppression'] as $index => $scenario) {
            $this->resetBudget();
            Contact::withTrashed()->where('account_id', $this->account->id)->forceDelete();
            Suppression::where('account_id', $this->account->id)->delete();
            $contact = in_array($scenario, ['known', 'deleted', 'identity', 'revoked', 'hard-delete'], true) ? Contact::create($base) : null;
            $response = $this->api(headers: ['HTTP_IDEMPOTENCY_KEY' => 'recipient-policy-000'.$index])->assertStatus(202);
            $id = $response->json('delivery_ids.0');
            $recipient = ApiMailRecipient::where('api_request_id', $response->json('request_id'))->sole();
            $this->assertSame($contact ? [$contact->id] : [], $recipient->contact_ids);
            match ($scenario) {
                'deleted' => $contact->delete(),
                'identity' => $contact->update(['email' => 'changed@example.test']),
                'revoked' => $contact->update(['consent_status' => 'revoked']),
                'hard-delete' => $contact->forceDelete(),
                'late-contact' => Contact::create(array_replace($base, ['consent_status' => 'pending'])),
                'late-suppression' => Suppression::create(['account_id' => $this->account->id, 'normalized_email' => $base['email'], 'source' => 'manual']),
                default => null,
            };
            // A replay remains the original acceptance despite live ineligibility.
            $this->api(headers: ['HTTP_IDEMPOTENCY_KEY' => 'recipient-policy-000'.$index])->assertStatus(202)->assertExactJson($response->json());
            $this->processApiDelivery($id);
            $delivery = Delivery::findOrFail($id);
            $eligible = in_array($scenario, ['known', 'direct'], true);
            $this->assertSame($eligible ? 'logged' : 'suppressed', $delivery->status, $scenario);
            $this->assertSame($eligible ? 1 : 0, $delivery->attempts, $scenario);
            if (! $contact && $scenario !== 'late-contact') {
                $this->assertSame(0, Contact::withTrashed()->where('account_id', $this->account->id)->count());
            }
        }
        $this->evidence('recipient-policy', ['initial_invalid_variants' => count($cases), 'transport_variants' => 8, 'api_attempts' => MailMessage::where('origin', 'api')->count(), 'contacts_fabricated' => false]);
    }

    public function test_account_isolation_cursor_status_and_sql_source_branches_are_enforced(): void
    {
        [$foreign, $foreignSender, $foreignKey] = $this->foreign();
        $receipt = $this->api()->assertStatus(202)->json();
        $this->api('GET', '/api/v1/mail/requests/'.$receipt['request_id'], key: $foreignKey)->assertStatus(404);
        $this->api('DELETE', '/api/v1/keys/'.$this->key['key']['id'], key: $foreignKey)->assertStatus(404);
        $payload = $this->payload();
        $this->api(body: $payload, key: $foreignKey)->assertStatus(404);
        $payload['sender_id'] = $foreignSender->id;
        $foreignReceipt = $this->api(body: $payload, key: $foreignKey)->assertStatus(202)->json();
        $this->assertNotSame($receipt['request_id'], $foreignReceipt['request_id']);
        $this->api('GET', '/api/v1/keys?cursor='.urlencode($this->api('GET', '/api/v1/keys?limit=1')->json('next_cursor') ?? 'invalid'), key: $foreignKey)->assertStatus(400);
        $prototype = (array) DB::table('deliveries')->where('id', $receipt['delivery_ids'][0])->first();
        $bad = [
            ['account_id' => $foreign->id], ['api_recipient_id' => null], ['api_request_id' => $foreignReceipt['request_id']], ['campaign_id' => 1], ['api_request_id' => null, 'api_recipient_id' => null],
        ];
        foreach ($bad as $change) {
            try {
                DB::table('deliveries')->insert(array_replace($prototype, ['id' => (string) Str::uuid()], $change));
                $this->fail('SQL accepted an invalid source branch.');
            } catch (QueryException $error) {
                $this->assertContains((string) $error->getCode(), ['23000', 'HY000']);
            }
        }
        $this->processApiDelivery($receipt['delivery_ids'][0]);
        $secondKey = $this->mint();
        $other = $this->api(key: $secondKey, headers: ['HTTP_IDEMPOTENCY_KEY' => 'same-account-source-0002'])->assertStatus(202)->json();
        $message = (array) DB::table('mail_messages')->where('delivery_id', $receipt['delivery_ids'][0])->sole();
        unset($message['id']);
        foreach ([['api_request_id' => $foreignReceipt['request_id']], ['api_credential_id' => AccountApiKey::where('public_id', $foreignKey['key']['id'])->value('id')], ['origin' => 'test'], ['api_credential_id' => null], ['api_request_id' => $other['request_id']], ['api_credential_id' => AccountApiKey::where('public_id', $secondKey['key']['id'])->value('id')]] as $change) {
            try {
                DB::table('mail_messages')->insert(array_replace($message, ['delivery_generation' => 99], $change));
                $this->fail('SQL accepted mixed attempt provenance.');
            } catch (QueryException $error) {
                $this->assertContains((string) $error->getCode(), ['23000', 'HY000']);
            }
        }
        try {
            MailMessage::where('delivery_id', $receipt['delivery_ids'][0])->firstOrFail()->fill(['actor_id' => $this->owner->id])->save();
            $this->fail('Model allowed interactive impersonation.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('impersonate', $error->getMessage());
        }
        $this->assertSame(3, Delivery::count());
        $this->evidence('source-constraints', ['rejected_delivery_branches' => count($bad), 'rejected_attempt_branches' => 6, 'actor_enforcement' => 'common transport plus model; not SQL CHECK']);
    }

    public function test_ui_grants_are_fresh_csrf_protected_and_secret_is_never_retrieved(): void
    {
        $this->get('/api-keys')->assertOk()->assertDontSee($this->key['secret']);
        $created = $this->post('/api-keys', ['name' => 'Browser fixture', 'scopes' => ['mail.send']]);
        $this->assertSame(200, $created->status());
        $this->assertStringContainsString('Guarda el secreto ahora', $created->getContent());
        $this->get('/api-keys')->assertOk()->assertDontSee('new-key-secret', false);
        Membership::where('account_id', $this->account->id)->where('user_id', $this->owner->id)->update(['role' => 'read-only']);
        $this->get('/api-keys')->assertOk();
        $this->post('/api-keys', ['name' => 'Denied', 'scopes' => ['mail.send']])->assertForbidden();
        $this->delete('/api-keys/'.$this->key['key']['id'])->assertForbidden();
        Membership::where('account_id', $this->account->id)->update(['role' => 'restricted', 'scopes' => json_encode(['api-keys.read', 'api-keys.write'])]);
        $this->post('/api-keys', ['name' => 'Escalation', 'scopes' => ['mail.send']])->assertForbidden();
        $creator = User::factory()->create();
        $key = app(AccountKeyService::class)->ui('api-keys.write', fn ($account, $ceiling) => app(AccountKeyService::class)->create($account, ['name' => 'Deleted creator', 'scopes' => ['api-keys.read']], $ceiling, actor: $creator->id));
        $creator->delete();
        $this->assertNull(AccountApiKey::where('public_id', $key['key']['id'])->value('created_by'));
        $this->api('GET', '/api/v1/keys', key: $key)->assertOk();
        // Laravel normally disables CSRF during tests; enable that middleware's real boundary.
        $original = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->post('/api-keys', ['name' => 'No CSRF', 'scopes' => ['api-keys.read']])->assertStatus(419);
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_creator_removal_and_user_deletion_preserve_key_and_actual_attempt_history(): void
    {
        $creator = User::factory()->create();
        $member = Membership::create(['account_id' => $this->account->id, 'user_id' => $creator->id, 'role' => 'admin', 'scopes' => []]);
        $this->actingAs($creator)->withSession(['account_id' => $this->account->id]);
        $key = app(AccountKeyService::class)->ui('api-keys.write', fn ($account, $ceiling) => app(AccountKeyService::class)->create($account, ['name' => 'Creator lifecycle fixture', 'scopes' => ['mail.send', 'activity.read', 'api-keys.read']], $ceiling, actor: $creator->id));
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
        app(TeamService::class)->removeMember($member->id);
        $this->actingAs($creator)->withSession(['account_id' => $this->account->id]);
        $this->get('/api-keys')->assertForbidden();
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
        $receipt = $this->api(key: $key)->assertStatus(202)->json();
        $message = $this->processApiDelivery($receipt['delivery_ids'][0]);
        $before = $message->fresh()->only(['id', 'origin', 'actor_id', 'api_request_id', 'api_credential_id', 'status']);
        $creator->delete();
        $this->assertNull(AccountApiKey::where('public_id', $key['key']['id'])->value('created_by'));
        $this->assertSame($before, $message->fresh()->only(array_keys($before)));
        $this->assertNull($before['actor_id']);
        $this->assertSame('api', $before['origin']);
        $this->api('GET', '/api/v1/mail/requests/'.$receipt['request_id'], key: $key)->assertOk()->assertJsonPath('counts.logged', 1);
        $this->evidence('creator-lifecycle', ['removed_membership' => true, 'deleted_creator' => true, 'key_still_valid' => true, 'attempt_preserved' => $before, 'actor_not_impersonated' => true]);
    }

    public function test_sender_snapshot_deletion_and_external_mode_block_pending_api_work_before_transport(): void
    {
        foreach (['authorization', 'snapshot', 'deleted', 'external'] as $index => $scenario) {
            MailSetting::updateOrCreate(['account_id' => $this->account->id], ['mode' => 'log']);
            $sender = MailSender::create(['account_id' => $this->account->id, 'email' => 'sender-'.$index.'@example.test', 'name' => 'Mutable sender fixture', 'lab_authorized' => false]);
            $payload = $this->payload();
            $payload['sender_id'] = $sender->id;
            $header = ['HTTP_IDEMPOTENCY_KEY' => 'sender-policy-case-000'.$index];
            $this->api(body: $payload, headers: $header)->assertStatus(422);
            $sender->update(['lab_authorized' => true]);
            $receipt = $this->api(body: $payload, headers: $header)->assertStatus(202)->json();
            match ($scenario) {
                'authorization' => $sender->update(['lab_authorized' => false]),
                'snapshot' => $sender->update(['name' => 'Changed sender']),
                'deleted' => $sender->delete(),
                'external' => MailSetting::where('account_id', $this->account->id)->update(['mode' => 'smtp']),
            };
            $this->assertNull($this->processApiDelivery($receipt['delivery_ids'][0]));
            $this->assertSame('blocked', Delivery::findOrFail($receipt['delivery_ids'][0])->status);
            $this->assertSame(0, Delivery::findOrFail($receipt['delivery_ids'][0])->attempts);
        }
        $this->assertSame(0, MailMessage::count());
        $this->evidence('sender-mode', ['blocked_variants' => 4, 'initially_unauthorized_rejected' => 4, 'smtp_attempts' => 0, 'external_enabled' => false]);
    }

    public function test_migration_preserves_campaign_sources_and_refuses_lossy_api_downgrade(): void
    {
        $template = EmailTemplate::create(['account_id' => $this->account->id, 'name' => 'Migration fixture', 'subject' => 'Fixture', 'html' => '', 'text' => 'Fixture']);
        $contact = Contact::create(['account_id' => $this->account->id, 'email' => 'migration@example.test', 'name' => 'Fixture', 'consent_status' => 'granted', 'consent_source' => 'Synthetic fixture', 'consented_at' => now('UTC')->subDay()]);
        $service = app(CampaignService::class);
        $campaign = $service->save($this->account, $this->owner->id, null, null, ['name' => 'Migration fixture', 'template_id' => $template->id, 'sender_id' => $this->sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]);
        $campaign = $service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $campaign = $service->authorizeCampaign($this->account, $this->owner->id, $campaign->id, $campaign->version, $campaign->reviewed_revision_id, true);
        app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
        $before = (array) DB::table('deliveries')->sole();
        $migration = require database_path('migrations/2026_10_24_000013_create_sending_api.php');
        $this->replayMigrationWindow('2026_10_24_000013_create_sending_api', function () use ($before): void {
            $this->assertSame($before['campaign_revision_id'], DB::table('deliveries')->sole()->campaign_revision_id);
        });
        $this->assertSame($before, (array) DB::table('deliveries')->sole());
        $this->key = $this->mint();
        $this->api()->assertStatus(202);
        try {
            $migration->down();
            $this->fail('Downgrade must not destroy accepted API work.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Archive API work', $error->getMessage());
        }
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(2, Delivery::count());
        $this->evidence('migration-roundtrip', ['campaign_preserved' => true, 'api_downgrade_refused_before_ddl' => true]);
    }

    public function test_sixty_five_bearer_boundary_cases_do_not_inherit_cookie_authority(): void
    {
        [$foreign] = $this->foreign();
        $receipt = $this->api()->assertStatus(202)->json();
        $target = $this->mint();
        $revoked = $this->mint();
        $expired = $this->mint();
        AccountApiKey::where('public_id', $revoked['key']['id'])->update(['revoked_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        AccountApiKey::where('public_id', $expired['key']['id'])->update(['expires_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $limitedSend = $this->mint(['activity.read']);
        $limitedOther = $this->mint(['mail.send']);
        $operations = [['POST', '/api/v1/mail/send', $this->payload(), 202], ['GET', '/api/v1/mail/requests/'.$receipt['request_id'], null, 200], ['GET', '/api/v1/keys', null, 200], ['POST', '/api/v1/keys', ['name' => 'Matrix child', 'scopes' => ['mail.send']], 201], ['DELETE', '/api/v1/keys/'.$target['key']['id'], null, 204]];
        $cases = [];
        foreach ($operations as $index => [$method, $url, $body, $success]) {
            $this->resetBudget();
            $raw = $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR);
            foreach (['missing-none', 'missing-admin', 'malformed', 'revoked', 'expired', 'mcp-format', 'google-format', 'query-body-only', 'invalid-header-with-alternative'] as $scenario) {
                if ($scenario === 'missing-none') {
                    auth()->logout();
                } else {
                    $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
                }
                $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'fixture-idempotency-0001'];
                $secret = match ($scenario) {
                    'revoked' => $revoked['secret'], 'expired' => $expired['secret'], 'mcp-format' => 'mcp-not-an-account-key', 'google-format' => 'google-not-an-account-key', default => 'invalid'
                };
                if (! in_array($scenario, ['missing-none', 'missing-admin', 'query-body-only'], true)) {
                    $server['HTTP_AUTHORIZATION'] = 'Bearer '.$secret;
                }
                $response = $this->call($method, $url.(in_array($scenario, ['query-body-only', 'invalid-header-with-alternative'], true) ? '?access_token=synthetic-rejected-alternative' : ''), [], [], [], $server, $raw);
                $this->assertSame(401, $response->status(), $scenario);
                $this->assertSame('no-store', $response->headers->get('Cache-Control'));
                $cases[] = ['operation' => $index, 'scenario' => $scenario, 'status' => $response->status()];
            }
            $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
            $response = $this->api($method, $url, $body, $index === 0 ? $limitedSend : $limitedOther);
            $this->assertSame(403, $response->status());
            $cases[] = ['operation' => $index, 'scenario' => 'missing-scope-admin-cookie', 'status' => 403];
            foreach (['admin-cookie-foreign', 'readonly-cookie-own', 'creator-offboarded'] as $scenario) {
                if ($scenario === 'readonly-cookie-own') {
                    Membership::where('account_id', $this->account->id)->where('user_id', $this->owner->id)->update(['role' => 'read-only']);
                } elseif ($scenario === 'creator-offboarded') {
                    Membership::where('account_id', $this->account->id)->where('user_id', $this->owner->id)->delete();
                }
                $this->withSession(['account_id' => $scenario === 'admin-cookie-foreign' ? $foreign->id : $this->account->id]);
                $response = $this->api($method, $url, $body);
                // Do not print a failed creation response containing its one-time secret.
                $this->assertSame($success, $response->status(), $scenario);
                $cases[] = ['operation' => $index, 'scenario' => $scenario, 'status' => $response->status()];
            }
            Membership::updateOrCreate(['account_id' => $this->account->id, 'user_id' => $this->owner->id], ['role' => 'admin', 'scopes' => []]);
        }
        $this->withSession(['account_id' => $this->account->id]);
        $this->assertCount(65, $cases);
        $this->evidence('bearer-65-cases', ['cases' => $cases, 'purpose_samples_are_invalid_formats' => true, 'mcp_google_protocol_implemented' => false]);
    }

    public function test_closed_key_inputs_sql_clock_expiry_and_all_authentication_operations(): void
    {
        foreach ([['name' => ' ', 'scopes' => ['mail.send']], ['name' => 'x', 'scopes' => ['*']], ['name' => 'x', 'scopes' => ['mail.send', 'mail.send']], ['name' => 'x', 'scopes' => []], ['name' => 'x', 'scopes' => ['mail.send'], 'expires_at' => '2026-02-30T12:00:00Z'], ['name' => 'x', 'scopes' => ['mail.send'], 'expires_at' => '2027-01-01T00:00:00+00:00'], ['name' => 'x', 'scopes' => ['mail.send'], 'expires_at' => '2020-01-01T00:00:00Z'], ['name' => 'x', 'scopes' => ['mail.send'], 'account_id' => $this->account->id]] as $body) {
            $this->api('POST', '/api/v1/keys', $body)->assertStatus(422);
        }
        $receipt = $this->api()->assertStatus(202)->json();
        $revoked = $this->mint();
        AccountApiKey::where('public_id', $revoked['key']['id'])->update(['revoked_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $expired = $this->mint();
        AccountApiKey::where('public_id', $expired['key']['id'])->update(['expires_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $this->resetBudget();
        $operations = [['POST', '/api/v1/mail/send', $this->payload()], ['GET', '/api/v1/mail/requests/'.$receipt['request_id'], null], ['GET', '/api/v1/keys', null], ['POST', '/api/v1/keys', ['name' => 'No secret output', 'scopes' => ['mail.send']]], ['DELETE', '/api/v1/keys/'.$revoked['key']['id'], null]];
        $cases = 0;
        foreach ($operations as [$method, $url, $body]) {
            $this->resetBudget();
            foreach ([$revoked, $expired, ['secret' => 'mcp-purpose-not-an-account-key'], ['secret' => 'google-access-not-an-account-key']] as $denied) {
                $this->api($method, $url, $body, $denied)->assertStatus(401)->assertHeader('Cache-Control', 'no-store');
                $cases++;
            }
            $wrong = $this->mint([$method === 'POST' && $url === '/api/v1/mail/send' ? 'activity.read' : 'mail.send']);
            $this->api($method, $url, $body, $wrong)->assertStatus(403);
            $cases++;
        }
        $this->evidence('authentication-matrix', ['operation_count' => 5, 'executed_invalid_expired_revoked_purpose_and_scope_cases' => $cases, 'mcp_google_credentials_implemented' => false]);
    }
}
