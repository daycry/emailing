<?php

namespace Tests\Feature;

use App\Livewire\Activity;
use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\ApiMailRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\McpClient;
use App\Models\Membership;
use App\Models\Suppression;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\AccountMailTransport;
use App\Services\DeliveryLifecycle;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\GrantService;
use App\Services\Mcp\ResponseBudget;
use App\Support\SendingApiInput;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

/** Transactional MCP acceptance, authorization, provenance and shared transport. */
class McpSendTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private array $accounts = [];

    protected function tearDown(): void
    {
        // Accepted synthetic requests must leave before the guarded migration down.
        // Never delete a grant to bypass its accepted-work foreign keys.
        if ($this->accounts !== []) {
            DB::table('api_mail_requests')->whereIn('account_id', $this->accounts)->delete();
        }
        parent::tearDown();
    }

    public function test_all_thirty_seven_operations_publish_the_pinned_catalogue(): void
    {
        [, , $bearer] = $this->fixture();
        $tools = $this->rpc($bearer, 'tools/list', ['per_page' => 100])->assertOk()->json('result.tools');
        $contract = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR);
        $expected = array_column($contract['operations'], 'name');
        $actual = array_column($tools, 'name');
        sort($expected);
        sort($actual);
        $this->assertCount(37, $actual);
        $this->assertSame($expected, $actual);
        foreach ($tools as $tool) {
            $pinned = ContractArguments::operation($tool['name']);
            $this->assertSame(array_intersect_key($pinned, array_flip(['name', 'description', 'inputSchema', 'outputSchema', 'annotations'])), $tool);
        }
    }

    public function test_zero_scope_connection_metadata_never_discloses_senders(): void
    {
        [$user, $account, , $sender] = $this->fixture();
        [, $grant, $bearer] = $this->mcpCredential($user, $account, []);
        $tools = $this->rpc($bearer, 'tools/list')->assertOk()->json('result.tools');
        $this->assertSame(['connection_info'], array_column($tools, 'name'));
        $data = $this->success($bearer, 'connection_info')['data'];
        $this->assertSame(['id' => $account->id, 'name' => $account->name], $data['account']);
        $this->assertSame(['user_id' => $user->id], $data['principal']);
        $this->assertSame($grant->id, $data['connection']['public_id']);
        $this->assertSame([], $data['connection']['granted_scopes']);
        $this->assertSame([], $data['connection']['effective_scopes']);
        $this->assertArrayNotHasKey('senders', $data);
        $this->failure($bearer, 'connection_info', ['senders' => (object) []], 'forbidden');
        $this->failure($bearer, 'mail_send', ['idempotency_key' => 'synthetic-mail-key-0001', 'message' => $this->message($sender)], 'forbidden');
        $this->assertDatabaseCount('api_mail_requests', 0);
    }

    public function test_mcp_and_api_replays_share_the_receipt_without_reattributing_first_commit(): void
    {
        [$user, $account, $bearer, $sender, $grant, $client] = $this->fixture();
        $message = $this->message($sender);
        $key = $this->apiKey($account->id);
        $idempotency = 'synthetic-shared-key-0001';
        $accepted = $this->success($bearer, 'mail_send', ['idempotency_key' => $idempotency, 'message' => $message]);
        $this->assertFalse($accepted['data']['replayed']);
        $request = ApiMailRequest::findOrFail($accepted['data']['request']['request_id']);
        $this->assertSame('mcp', $request->origin);
        $this->assertNull($request->credential_id);
        $this->assertSame($grant->id, $request->mcp_grant_id);
        $this->assertSame($client->id, $request->mcp_client_id);
        $before = $request->getAttributes();
        $this->api($key, $idempotency, $message)->assertStatus(202)->assertExactJson($accepted['data']['request'])->assertHeader('Idempotency-Replayed', 'true');
        $again = $this->success($bearer, 'mail_send', ['idempotency_key' => $idempotency, 'message' => $message]);
        $this->assertTrue($again['data']['replayed']);
        $this->assertSameReceipt($accepted['data']['request'], $again['data']['request']);
        $this->assertSame($before, $request->fresh()->getAttributes());
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_preregistered_client_identifiers_are_not_truncated_in_work_or_audit(): void
    {
        [$user, $account, , $sender] = $this->fixture();
        $client = McpClient::create(['id' => 'synthetic-'.str_repeat('c', 96), 'issuer' => config('mcp.issuer'),
            'name' => 'Synthetic preregistered client', 'redirect_uris' => ['http://127.0.0.1:18743/callback']]);
        [, $grant, $bearer] = $this->mcpCredential($user, $account, client: $client);
        $dto = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-long-client-0001', 'message' => $this->message($sender)]);
        $request = ApiMailRequest::findOrFail($dto['data']['request']['request_id']);
        $this->assertSame($client->id, $request->mcp_client_id);
        $this->assertSame($grant->id, $request->mcp_grant_id);
        $this->assertDatabaseHas('mcp_audit_events', ['client_id' => $client->id, 'grant_id' => $grant->id,
            'operation' => 'mail_send', 'correlation_id' => $dto['meta']['trace_id'], 'outcome' => 'succeeded']);
        $audit = DB::table('audit_events')->where('account_id', $account->id)->where('action', 'mcp.mail.request.accepted')->sole();
        $metadata = json_decode($audit->metadata, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($request->id, $metadata['request_id']);
        $this->assertSame($client->id, $metadata['client_id']);
        $this->assertSame($grant->id, $metadata['grant_id']);
        $this->assertSame($dto['meta']['trace_id'], $metadata['trace_id']);
        $this->assertFalse($metadata['replayed']);
    }

    public function test_api_first_commit_remains_api_after_an_mcp_replay_and_transport(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $key = $this->apiKey($account->id);
        $message = $this->message($sender);
        $idempotency = 'synthetic-api-first-0001';
        $receipt = $this->api($key, $idempotency, $message)->assertStatus(202)->json();
        $principal = AccountApiKey::where('public_id', $key['key']['id'])->firstOrFail();
        $accepted = $this->success($bearer, 'mail_send', ['idempotency_key' => $idempotency, 'message' => $message]);
        $this->assertTrue($accepted['data']['replayed']);
        $this->assertSameReceipt($receipt, $accepted['data']['request']);
        $request = ApiMailRequest::findOrFail($receipt['request_id']);
        $this->assertSame('api', $request->origin);
        $this->assertSame($principal->id, $request->credential_id);
        $this->assertNull($request->mcp_grant_id);
        $record = $this->transport($receipt['delivery_ids'][0]);
        $this->assertSame('api', $record->origin);
        $this->assertNull($record->actor_id);
        $this->assertSame($principal->id, $record->api_credential_id);
        $this->assertNull($record->mcp_grant_id);
        $this->assertSame('logged', $record->status);
    }

    public function test_conflicting_payload_cannot_create_partial_work_on_either_entrypoint(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $message = $this->message($sender);
        $idempotency = 'synthetic-conflict-key-0001';
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => $idempotency, 'message' => $message])['data']['request'];
        $message['content']['subject'] = 'Different synthetic content';
        $this->failure($bearer, 'mail_send', ['idempotency_key' => $idempotency, 'message' => $message], 'idempotency_conflict');
        $this->api($this->apiKey($account->id), $idempotency, $message)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->assertSameReceipt($receipt, ApiMailRequest::sole()->receipt);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
    }

    public function test_template_scope_is_current_even_for_replay_after_template_deletion(): void
    {
        [$user, $account, $bearer, $sender] = $this->fixture();
        $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'Synthetic mail template', 'subject' => 'Hello {{contact.name}}', 'html' => '', 'text' => 'Hello {{contact.email}}']);
        $message = $this->message($sender);
        $message['content'] = ['type' => 'template', 'template_id' => $template->id];
        $arguments = ['idempotency_key' => 'synthetic-template-key-0001', 'message' => $message];
        $receipt = $this->success($bearer, 'mail_send', $arguments)['data']['request'];
        $template->delete();
        $again = $this->success($bearer, 'mail_send', $arguments);
        $this->assertTrue($again['data']['replayed']);
        $this->assertSameReceipt($receipt, $again['data']['request']);
        [, , $sendOnly] = $this->mcpCredential($user, $account, ['mail.send']);
        $this->failure($sendOnly, 'mail_send', $arguments, 'forbidden');
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_late_suppression_blocks_the_common_start_without_a_transport_attempt(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $accepted = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-suppression-0001', 'message' => $this->message($sender)]);
        $receipt = $accepted['data']['request'];
        Suppression::create(['account_id' => $account->id, 'normalized_email' => 'recipient@example.test', 'source' => 'manual']);
        $this->assertNull($this->transport($receipt['delivery_ids'][0]));
        $status = $this->success($bearer, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request'];
        $this->assertSame(1, $status['counts']['suppressed']);
        $this->assertSame(0, $status['deliveries'][0]['attempts']);
        $this->assertSame('not_available', $status['delivery_feedback']);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_response_budget_rolls_back_a_new_acceptance_before_commit(): void
    {
        [, , $bearer, $sender] = $this->fixture();
        $arguments = ['idempotency_key' => 'synthetic-budget-key-0001', 'message' => $this->message($sender)];
        $original = app(ResponseBudget::class);
        app()->instance(ResponseBudget::class, new ResponseBudget(300));
        try {
            $this->failure($bearer, 'mail_send', $arguments, 'result_too_large');
            $this->assertDatabaseCount('api_mail_requests', 0);
            $this->assertDatabaseCount('api_mail_recipients', 0);
            $this->assertDatabaseCount('deliveries', 0);
            $this->assertDatabaseCount('delivery_outbox', 0);
        } finally {
            app()->instance(ResponseBudget::class, $original);
        }
        $this->assertFalse($this->success($bearer, 'mail_send', $arguments)['data']['replayed']);
    }

    public function test_deleted_actor_and_pruned_credentials_preserve_accepted_work_tombstones(): void
    {
        [, $account, $reader, $sender] = $this->fixture();
        $actor = User::factory()->create(['email' => 'synthetic-deleted-actor@example.test']);
        Membership::create(['account_id' => $account->id, 'user_id' => $actor->id, 'role' => 'admin', 'scopes' => []]);
        [$token, $grant, $bearer, $client] = $this->mcpCredential($actor, $account);
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-actor-history-0001', 'message' => $this->message($sender)])['data']['request'];
        $record = $this->transport($receipt['delivery_ids'][0]);
        $this->assertSame('mcp', $record->origin);
        $this->assertSame($actor->id, $record->actor_id);
        $this->assertSame($grant->id, $record->mcp_grant_id);
        app(GrantService::class)->revoke($grant->id, $actor->id);
        $actor->delete();
        $this->assertNull($record->fresh()->actor_id);
        $this->assertNull($grant->fresh()->user_id);
        // Simulate retention age only for this already revoked synthetic grant.
        DB::table('mcp_grants')->where('id', $grant->id)->update(['revoked_at' => now()->subDays(95)]);
        DB::table('personal_access_tokens')->where('id', $token->id)->update(['revoked_at' => now()->subDays(95)]);
        $this->artisan('mcp:prune', ['--limit' => '100'])->assertExitCode(0);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('mcp_grants', ['id' => $grant->id, 'user_id' => null]);
        $this->assertDatabaseHas('mcp_clients', ['id' => $client->id]);
        $this->assertSameReceipt($receipt, ApiMailRequest::findOrFail($receipt['request_id'])->receipt);
        $this->assertSame('logged', Delivery::findOrFail($receipt['delivery_ids'][0])->status);
        $status = $this->success($reader, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request'];
        $this->assertSame(1, $status['counts']['logged']);
        $rows = $this->success($reader, 'activity_list', ['filters' => ['population' => 'mcp']])['data']['items'];
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['actor_name']);
        $this->assertSame(['kind' => 'mcp_grant', 'public_id' => $grant->id, 'client_id' => $client->id, 'client_name' => $client->name], $rows[0]['credential']);
    }

    public function test_sql_rejects_mixed_request_and_attempt_principals_without_partial_rows(): void
    {
        [$user, $account, $bearer, $sender] = $this->fixture();
        $message = $this->message($sender);
        $first = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-sql-first-0001', 'message' => $message])['data']['request'];
        [, $otherGrant, $otherBearer, $otherClient] = $this->mcpCredential($user, $account);
        $second = $this->success($otherBearer, 'mail_send', ['idempotency_key' => 'synthetic-sql-second-0001', 'message' => $message])['data']['request'];
        $this->transport($first['delivery_ids'][0]);
        $attempt = (array) DB::table('mail_messages')->where('delivery_id', $first['delivery_ids'][0])->sole();
        unset($attempt['id']);
        foreach ([['origin' => 'api'], ['mcp_grant_id' => null], ['mcp_client_id' => null],
            ['mcp_grant_id' => $otherGrant->id, 'mcp_client_id' => $otherClient->id],
            ['api_request_id' => $second['request_id']], ['origin' => 'test']] as $change) {
            try {
                DB::table('mail_messages')->insert(array_replace($attempt, ['delivery_generation' => 99], $change));
                $this->fail('SQL accepted a mixed attempt principal.');
            } catch (QueryException $error) {
                $this->assertContains((string) $error->getCode(), ['23000', 'HY000']);
            }
        }
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertDatabaseCount('api_mail_requests', 2);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('delivery_outbox', 2);
    }

    public function test_tenants_cannot_resolve_foreign_senders_templates_or_requests(): void
    {
        [, $firstAccount, $firstBearer, $firstSender] = $this->fixture();
        [, $secondAccount, $secondBearer, $secondSender] = $this->fixture();
        $idempotency = 'synthetic-tenant-key-0001';
        $first = $this->success($firstBearer, 'mail_send', ['idempotency_key' => $idempotency,
            'message' => $this->message($firstSender)])['data']['request'];
        $this->failure($secondBearer, 'mail_send', ['idempotency_key' => 'synthetic-foreign-sender-0001',
            'message' => $this->message($firstSender)], 'not_found');
        $template = EmailTemplate::create(['account_id' => $firstAccount->id, 'name' => 'Synthetic foreign template',
            'subject' => 'Synthetic', 'html' => '', 'text' => 'Synthetic body']);
        $foreignTemplate = $this->message($secondSender);
        $foreignTemplate['content'] = ['type' => 'template', 'template_id' => $template->id];
        $this->failure($secondBearer, 'mail_send', ['idempotency_key' => 'synthetic-foreign-template-0001',
            'message' => $foreignTemplate], 'not_found');
        $this->failure($secondBearer, 'mail_request_get', ['request_id' => $first['request_id']], 'not_found');
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('api_mail_recipients', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
        $second = $this->success($secondBearer, 'mail_send', ['idempotency_key' => $idempotency,
            'message' => $this->message($secondSender)])['data']['request'];
        $this->assertNotSame($first['request_id'], $second['request_id']);
        $this->assertSame($firstAccount->id, ApiMailRequest::findOrFail($first['request_id'])->account_id);
        $this->assertSame($secondAccount->id, ApiMailRequest::findOrFail($second['request_id'])->account_id);
        $this->assertDatabaseCount('api_mail_requests', 2);
        $this->assertDatabaseCount('api_mail_recipients', 2);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('delivery_outbox', 2);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_membership_demotion_denies_a_replay_without_reattributing_the_request(): void
    {
        [$user, $account, $bearer, $sender] = $this->fixture();
        $arguments = ['idempotency_key' => 'synthetic-demotion-key-0001', 'message' => $this->message($sender)];
        $receipt = $this->success($bearer, 'mail_send', $arguments)['data']['request'];
        $before = ApiMailRequest::findOrFail($receipt['request_id'])->getAttributes();
        Membership::where('account_id', $account->id)->where('user_id', $user->id)->firstOrFail()
            ->update(['role' => 'read-only', 'scopes' => []]);
        $this->failure($bearer, 'mail_send', $arguments, 'forbidden');
        $this->assertSame($before, ApiMailRequest::findOrFail($receipt['request_id'])->getAttributes());
        $this->assertSame(1, $this->success($bearer, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request']['counts']['pending']);
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_revocation_denies_new_calls_and_replays_but_keeps_already_accepted_work(): void
    {
        [$user, , $bearer, $sender, $grant] = $this->fixture();
        $arguments = ['idempotency_key' => 'synthetic-revoked-key-0001', 'message' => $this->message($sender)];
        $receipt = $this->success($bearer, 'mail_send', $arguments)['data']['request'];
        $before = ApiMailRequest::findOrFail($receipt['request_id'])->getAttributes();
        app(GrantService::class)->revoke($grant->id, $user->id);
        foreach ([$arguments, array_replace($arguments, ['idempotency_key' => 'synthetic-revoked-new-0001'])] as $input) {
            $this->rpc($bearer, 'tools/call', ['name' => 'mail_send', 'arguments' => (object) $input])->assertStatus(401);
        }
        $this->rpc($bearer, 'tools/call', ['name' => 'mail_request_get',
            'arguments' => (object) ['request_id' => $receipt['request_id']]])->assertStatus(401);
        $this->assertSame($before, ApiMailRequest::findOrFail($receipt['request_id'])->getAttributes());
        $record = $this->transport($receipt['delivery_ids'][0]);
        $this->assertNotNull($record);
        $this->assertSame('logged', $record->status);
        $this->assertSame('mcp', $record->origin);
        $this->assertSame($grant->id, $record->mcp_grant_id);
        $this->assertSame($user->id, $record->actor_id);
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('api_mail_recipients', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('mail_messages', 1);
    }

    public function test_consent_revoked_after_acceptance_prevents_a_transport_attempt(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $contact = Contact::create(['account_id' => $account->id, 'email' => 'recipient@example.test',
            'name' => 'Synthetic consent recipient', 'consent_status' => 'granted',
            'consent_source' => 'Synthetic form', 'consented_at' => now('UTC')->subDay()]);
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-late-consent-0001',
            'message' => $this->message($sender)])['data']['request'];
        $contact->update(['consent_status' => 'revoked']);
        $this->assertNull($this->transport($receipt['delivery_ids'][0]));
        $delivery = Delivery::findOrFail($receipt['delivery_ids'][0]);
        $this->assertSame('suppressed', $delivery->status);
        $this->assertSame('recipient_suppressed', $delivery->diagnostic);
        $this->assertSame(0, $delivery->attempts);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_a_sender_changed_after_acceptance_cannot_use_the_stored_authorization(): void
    {
        [, , $bearer, $sender] = $this->fixture();
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-late-sender-0001',
            'message' => $this->message($sender)])['data']['request'];
        $sender->update(['lab_authorized' => false]);
        $this->assertNull($this->transport($receipt['delivery_ids'][0]));
        $delivery = Delivery::findOrFail($receipt['delivery_ids'][0]);
        $this->assertSame('blocked', $delivery->status);
        $this->assertSame('sender_or_transport_changed', $delivery->diagnostic);
        $this->assertSame(0, $delivery->attempts);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_mcp_activity_status_ui_and_csv_preserve_one_logical_delivery_after_api_replay(): void
    {
        [$user, $account, $bearer, $sender, $grant, $client] = $this->fixture();
        $key = $this->apiKey($account->id);
        $arguments = ['idempotency_key' => 'synthetic-activity-key-0001', 'message' => $this->message($sender)];
        $receipt = $this->success($bearer, 'mail_send', $arguments)['data']['request'];
        $requestId = $receipt['request_id'];
        $recordKey = 'delivery:'.$receipt['delivery_ids'][0];
        $credential = ['kind' => 'mcp_grant', 'public_id' => $grant->id,
            'client_id' => $client->id, 'client_name' => $client->name];
        $this->api($key, $arguments['idempotency_key'], $arguments['message'])
            ->assertStatus(202)->assertExactJson($receipt)->assertHeader('Idempotency-Replayed', 'true');

        $pending = $this->success($bearer, 'activity_list', ['filters' => ['population' => 'mcp'], 'limit' => 100]);
        $this->assertCount(1, $pending['data']['items']);
        $row = $pending['data']['items'][0];
        $this->assertSame($recordKey, $row['record_key']);
        $this->assertSame('pending', $row['status']);
        $this->assertSame('mcp', $row['population']);
        $this->assertSame(0, $row['durable_starts']);
        $this->assertSame($requestId, $row['request_id']);
        $this->assertSame($credential, $row['credential']);
        $this->assertSame($user->name, $row['actor_name']);
        $this->assertSame([], $this->success($bearer, 'activity_attempts', ['record_key' => $recordKey])['data']['items']);

        $message = $this->transport($receipt['delivery_ids'][0]);
        $this->assertSame('logged', $message->status);
        $this->assertSame('mcp', $message->origin);
        $this->assertSame($user->id, (int) $message->actor_id);
        $this->assertNull($message->api_credential_id);
        $this->assertSame($grant->id, $message->mcp_grant_id);
        $this->assertSame($client->id, $message->mcp_client_id);
        $status = $this->success($bearer, 'mail_request_get', ['request_id' => $requestId])['data']['request'];
        $this->assertSame(1, $status['counts']['logged']);
        $this->assertSame(1, $status['deliveries'][0]['attempts']);
        $attempts = $this->success($bearer, 'activity_attempts', ['record_key' => $recordKey])['data']['items'];
        $this->assertCount(1, $attempts);
        $this->assertSame((int) $message->id, $attempts[0]['id']);
        $this->assertSame($credential, $attempts[0]['credential']);
        $this->assertSame($requestId, $attempts[0]['request_id']);
        $this->assertSame('logged', $attempts[0]['transport_status']);
        $metrics = $this->success($bearer, 'activity_metrics')['data']['populations'];
        $this->assertSame(1, $metrics['mcp']['total']);
        $this->assertSame(1, $metrics['mcp']['states']['logged']);
        foreach (['api', 'campaign', 'test', 'unknown'] as $population) {
            $this->assertSame(0, $metrics[$population]['total']);
        }
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('mail_messages', 1);

        [$otherUser, $otherAccount] = $this->mcpAccount();
        $this->accounts[] = $otherAccount->id;
        [, , $otherBearer] = $this->mcpCredential($otherUser, $otherAccount);
        $this->failure($otherBearer, 'activity_attempts', ['record_key' => $recordKey], 'not_found');
        $this->failure($otherBearer, 'mail_request_get', ['request_id' => $requestId], 'not_found');
        $this->assertSame([], $this->success($otherBearer, 'activity_list', ['filters' => ['population' => 'mcp']])['data']['items']);

        $this->actingAs($user)->withSession(['account_id' => $account->id]);
        Livewire::test(Activity::class)->set('population', 'mcp')
            ->call('open', $recordKey)->assertSee($client->name)->assertSee($grant->id)->assertSee($requestId);
        $csv = $this->get('/activity/export?population=mcp')->assertOk()->streamedContent();
        $this->assertStringContainsString($requestId, $csv);
        $this->assertStringContainsString($grant->id, $csv);
        $this->assertStringContainsString($client->name, $csv);
        $this->assertStringNotContainsString($bearer, $csv);
        $this->assertStringNotContainsString($key['secret'], $csv);
        $this->assertSame(2, count(array_filter(explode("\n", trim($csv)))));
    }

    /** JSON receipt objects are unordered; delivery_ids remains strictly ordered. */
    public function test_raw_http_ids_defaults_and_template_content_share_api_fingerprints(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $key = $this->apiKey($account->id);
        $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'Synthetic raw template',
            'subject' => '  Literal ñ 7e1  ', 'html' => '', 'text' => "Body\r\n{{contact.email}}"]);
        foreach (['inline', 'template'] as $kind) {
            $idempotency = 'synthetic-raw-canonical-'.$kind;
            $recipients = [['email' => " \tRecipient@EXAMPLE.test\t "]];
            $content = $kind === 'inline'
                ? ['type' => 'inline', 'subject' => '  Literal ñ 7e1  ', 'text' => "Body\r\n{{ untouched }}"]
                : ['type' => 'template', 'template_id' => $template->id];
            $rawContent = $kind === 'inline' ? json_encode($content, JSON_THROW_ON_ERROR)
                : '{"type":"template","template_id":'.$template->id.'e0}';
            $raw = '{"idempotency_key":'.json_encode($idempotency, JSON_THROW_ON_ERROR).',"message":{'
                .'"sender_id":'.$sender->id.'.0,"recipients":'.json_encode($recipients, JSON_THROW_ON_ERROR)
                .',"content":'.$rawContent.'}}';
            $response = $this->rawMail($bearer, $raw)->assertOk()->assertJsonPath('result.isError', false);
            $receipt = $response->json('result.structuredContent.data.request');
            $this->assertFalse($response->json('result.structuredContent.data.replayed'));
            $message = ['sender_id' => $sender->id, 'recipients' => $recipients, 'content' => $content];
            $this->api($key, $idempotency, $message)->assertStatus(202)->assertExactJson($receipt)
                ->assertHeader('Idempotency-Replayed', 'true');
            $canonical = SendingApiInput::send(json_decode(json_encode($message, JSON_THROW_ON_ERROR)));
            $request = ApiMailRequest::findOrFail($receipt['request_id']);
            $this->assertSame(SendingApiInput::fingerprint($canonical), $request->fingerprint);
            $recipient = DB::table('api_mail_recipients')->where('api_request_id', $request->id)->sole();
            $this->assertSame('recipient@example.test', $recipient->email);
            $this->assertSame('', $recipient->name);
            $this->assertSame('  Literal ñ 7e1  ', $recipient->subject);
            $this->assertSame($kind === 'inline' ? "Body\r\n{{ untouched }}" : "Body\r\nrecipient@example.test", $recipient->text);
        }
        $this->assertDatabaseCount('api_mail_requests', 2);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('delivery_outbox', 2);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_raw_http_invalid_integer_lexemes_and_common_input_policy_leave_no_work(): void
    {
        [, , $bearer, $sender] = $this->fixture();
        $message = $this->message($sender);
        $cases = [];
        foreach (['9007199254740990.1', '"7"', '1e999999999', '0', 'null', '7.1'] as $lexeme) {
            $cases[] = '{"sender_id":'.$lexeme.',"recipients":[{"email":"recipient@example.test"}],'
                .'"content":{"type":"inline","subject":"Synthetic","text":"Body"}}';
        }
        foreach ([
            ['recipients' => [['email' => 'recipient@example.test'], ['email' => ' RECIPIENT@EXAMPLE.test ']]],
            ['recipients' => [['email' => 'recipient@example.test', 'name' => null]]],
            ['content' => ['type' => 'inline', 'subject' => "Header\nInjection", 'text' => 'Body']],
            ['account_id' => 7],
        ] as $change) {
            $cases[] = json_encode(array_replace($message, $change), JSON_THROW_ON_ERROR);
        }
        foreach ($cases as $index => $rawMessage) {
            $raw = '{"idempotency_key":"synthetic-raw-invalid-'.$index.'","message":'.$rawMessage.'}';
            $response = $this->rawMail($bearer, $raw)->assertOk()->assertJsonPath('result.isError', true);
            $error = json_decode($response->json('result.content.0.text'), true, 64, JSON_THROW_ON_ERROR);
            $this->assertSame('validation_failed', $error['error']['code']);
            $this->assertSame('not_applied', $error['error']['outcome']);
        }
        foreach (['api_mail_requests', 'api_mail_recipients', 'deliveries', 'delivery_outbox', 'mail_messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_sql_request_branches_reject_foreign_or_mixed_authority(): void
    {
        [$user, $account, $bearer, $sender] = $this->fixture();
        [, $grant, , $client] = $this->mcpCredential($user, $account);
        [, $foreignAccount, , , $foreignGrant, $foreignClient] = $this->fixture();
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-request-sql-0001',
            'message' => $this->message($sender)])['data']['request'];
        $original = (array) DB::table('api_mail_requests')->where('id', $receipt['request_id'])->sole();
        $key = $this->apiKey($account->id);
        $credential = AccountApiKey::where('public_id', $key['key']['id'])->value('id');
        foreach ([
            ['origin' => 'api'], ['credential_id' => $credential], ['mcp_grant_id' => null],
            ['mcp_client_id' => null], ['origin' => 'unknown'],
            ['mcp_grant_id' => $foreignGrant->id, 'mcp_client_id' => $foreignClient->id],
            ['mcp_grant_id' => $grant->id, 'mcp_client_id' => $original['mcp_client_id']],
            ['account_id' => $foreignAccount->id, 'mcp_grant_id' => $grant->id, 'mcp_client_id' => $client->id],
        ] as $index => $change) {
            try {
                DB::table('api_mail_requests')->insert(array_replace($original,
                    ['id' => (string) Str::uuid(), 'idempotency_hash' => hash('sha256', 'synthetic-invalid-request-'.$index)], $change));
                $this->fail('SQL accepted invalid request authority.');
            } catch (QueryException $error) {
                $this->assertContains((int) ($error->errorInfo[1] ?? 0), [1452, 3819]);
            }
        }
        $this->assertSame($original, (array) DB::table('api_mail_requests')->where('id', $receipt['request_id'])->sole());
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
    }

    public function test_shared_schema_downgrade_refuses_accepted_mcp_work_before_ddl(): void
    {
        [, , $bearer, $sender] = $this->fixture();
        $receipt = $this->success($bearer, 'mail_send', ['idempotency_key' => 'synthetic-downgrade-0001',
            'message' => $this->message($sender)])['data']['request'];
        $before = [];
        foreach (['api_mail_requests', 'mail_messages'] as $table) {
            $before[$table] = (array) DB::selectOne('SHOW CREATE TABLE '.$table);
        }
        $migration = require database_path('migrations/2026_10_26_000007_share_transactional_mail_origins.php');
        try {
            $migration->down();
            $this->fail('MCP work must prevent a lossy downgrade.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Archive MCP work', $error->getMessage());
        }
        foreach ($before as $table => $schema) {
            $this->assertSame($schema, (array) DB::selectOne('SHOW CREATE TABLE '.$table));
        }
        $this->assertSameReceipt($receipt, ApiMailRequest::findOrFail($receipt['request_id'])->receipt);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
    }

    public function test_http_hundred_recipient_batches_preserve_order_on_inline_and_template_replay(): void
    {
        [, $account, $bearer, $sender] = $this->fixture();
        $key = $this->apiKey($account->id);
        $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'Synthetic hundred template',
            'subject' => 'Synthetic ñ', 'html' => '', 'text' => 'Hello {{contact.email}}']);
        foreach (['inline', 'template'] as $kind) {
            $message = ['sender_id' => $sender->id,
                'recipients' => array_map(fn (int $i): array => ['email' => ' Recipient'.$i.'@EXAMPLE.test ', 'name' => 'Ñ synthetic '.$i], range(1, 100)),
                'content' => $kind === 'inline' ? ['type' => 'inline', 'subject' => 'Synthetic ñ', 'text' => 'Literal {{ untouched }}']
                    : ['type' => 'template', 'template_id' => $template->id]];
            $arguments = ['idempotency_key' => 'synthetic-hundred-'.$kind, 'message' => $message];
            $receipt = $this->success($bearer, 'mail_send', $arguments)['data']['request'];
            $this->assertCount(100, $receipt['delivery_ids']);
            $this->api($key, $arguments['idempotency_key'], $message)->assertStatus(202)->assertExactJson($receipt)
                ->assertHeader('Idempotency-Replayed', 'true');
            $rows = DB::table('api_mail_recipients as p')->join('deliveries as d', fn ($join) => $join
                ->on('d.api_recipient_id', '=', 'p.id')->on('d.account_id', '=', 'p.account_id')->on('d.api_request_id', '=', 'p.api_request_id'))
                ->where('p.account_id', $account->id)->where('p.api_request_id', $receipt['request_id'])->orderBy('p.position')
                ->get(['p.email', 'p.name', 'd.id as delivery_id']);
            $this->assertSame(array_map(fn (int $i): string => 'recipient'.$i.'@example.test', range(1, 100)), $rows->pluck('email')->all());
            $this->assertSame($receipt['delivery_ids'], $rows->pluck('delivery_id')->all());
            $this->assertSame('Ñ synthetic 1', $rows[0]->name);
            $this->assertSame('Ñ synthetic 100', $rows[99]->name);
            $arguments['message']['recipients'] = array_reverse($message['recipients']);
            $this->failure($bearer, 'mail_send', $arguments, 'idempotency_conflict');
        }
        $this->assertDatabaseCount('api_mail_requests', 2);
        $this->assertDatabaseCount('deliveries', 200);
        $this->assertDatabaseCount('delivery_outbox', 200);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    private function rawMail(string $bearer, string $arguments)
    {
        $body = '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"mail_send",'
            .'"arguments":'.$arguments.',"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28",'
            .'"io.modelcontextprotocol/clientCapabilities":{}}}}';

        return $this->call('POST', '/mcp', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$bearer,
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28', 'HTTP_MCP_METHOD' => 'tools/call', 'HTTP_MCP_NAME' => 'mail_send'], $body);
    }

    private function assertSameReceipt(array $expected, array $actual): void
    {
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
    }

    private function fixture(): array
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $this->accounts[] = $account->id;
        [, $grant, $bearer, $client] = $this->mcpCredential($user, $account);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Synthetic sender', 'lab_authorized' => true]);
        Storage::fake('mail-artifacts');
        config(['delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000]);

        return [$user, $account, $bearer, $sender, $grant, $client];
    }

    private function message(MailSender $sender): array
    {
        return ['sender_id' => $sender->id, 'recipients' => [['email' => 'recipient@example.test', 'name' => 'Synthetic recipient']],
            'content' => ['type' => 'inline', 'subject' => '  Synthetic 7e1  ', 'text' => "Body\r\n{{ untouched }}"]];
    }

    private function apiKey(int $account): array
    {
        return DB::transaction(function () use ($account): array {
            Account::whereKey($account)->lockForUpdate()->firstOrFail();

            return app(AccountKeyService::class)->create($account, ['name' => 'Synthetic shared API key', 'scopes' => SendingApiInput::SCOPES], SendingApiInput::SCOPES);
        });
    }

    private function api(array $key, string $idempotency, array $message)
    {
        return $this->call('POST', '/api/v1/mail/send', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$key['secret'], 'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $idempotency], json_encode($message, JSON_THROW_ON_ERROR));
    }

    private function transport(string $delivery): ?MailMessage
    {
        $event = DeliveryOutbox::where('delivery_id', $delivery)->where('type', 'delivery.ready')->orderByDesc('generation')->firstOrFail();
        $claim = app(DeliveryLifecycle::class)->claim(['account_id' => $event->account_id, 'delivery_id' => $event->delivery_id, 'event_id' => $event->id, 'generation' => $event->generation]);
        $this->assertNotNull($claim);

        return app(AccountMailTransport::class)->sendDelivery($claim);
    }

    private function success(string $bearer, string $name, array $arguments = []): array
    {
        $response = $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments])->assertOk();
        $error = json_decode($response->json('result.content.0.text') ?? '{}', true);
        $this->assertFalse($response->json('result.isError'), $name.' '.($error['error']['code'] ?? 'invalid_result'));
        $dto = $response->json('result.structuredContent');
        $this->assertSame($dto, json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertTrue(ContractArguments::valid(json_decode(json_encode($dto, JSON_THROW_ON_ERROR)), ContractArguments::operation($name)['outputSchema']));

        return $dto;
    }

    private function failure(string $bearer, string $name, array $arguments, string $code): array
    {
        $response = $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments])->assertOk()->assertJsonPath('result.isError', true);
        $dto = json_decode($response->json('result.content.0.text'), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame($code, $dto['error']['code']);
        $this->assertSame('not_applied', $dto['error']['outcome']);
        $this->assertFalse($dto['error']['can_repeat_operation']);

        return $dto;
    }

    private function rpc(string $bearer, string $method, array $params = [])
    {
        $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
        $headers = ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if ($method === 'tools/call') {
            $headers['Mcp-Name'] = $params['name'];
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }
}
