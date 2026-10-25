<?php

namespace Tests\Feature;

use App\Models\ContactList;
use App\Models\McpAuditEvent;
use App\Models\McpAuthorizationRequest;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\RegistryCursor;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpConsentToolsTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private function rpc(string $bearer, string $method, array $params = [])
    {
        $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
        $headers = ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if ($method === 'tools/call') {
            $headers['Mcp-Name'] = $params['name'];
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }

    private function callTool(string $bearer, string $name, array $arguments)
    {
        return $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    private function error($response, string $code): array
    {
        $response->assertOk()->assertJsonPath('result.isError', true)->assertJsonMissingPath('result.structuredContent');
        $dto = json_decode($response->json('result.content.0.text'), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($code, $dto['error']['code']);
        $this->assertSame('not_applied', $dto['error']['outcome']);

        return $dto;
    }

    public function test_productive_tools_bind_account_to_grant_and_project_closed_dtos_with_audit(): void
    {
        $this->mcpConfiguration();
        [$user, $accountA] = $this->mcpAccount();
        [, $accountB] = $this->mcpAccount($user);
        [, , $bearer] = $this->mcpCredential($user, $accountB);
        $foreign = ContactList::create(['account_id' => $accountA->id, 'name' => 'Foreign list', 'description' => 'Private A']);
        $this->actingAs($user)->withSession(['account_id' => $accountA->id]);
        $this->rpc($bearer, 'tools/list')->assertOk()->assertJsonCount(2, 'result.tools');
        $created = $this->callTool($bearer, 'lists_create', ['list' => ['name' => '  Exact text  ', 'description' => '0']])->assertOk()->assertJsonPath('result.isError', false);
        $dto = $created->json('result.structuredContent');
        $this->assertSame(['data', 'meta'], array_keys($dto));
        $this->assertSame(['id', 'name', 'description', 'created_at', 'updated_at'], array_keys($dto['data']['list']));
        $id = $dto['data']['list']['id'];
        $this->assertSame('  Exact text  ', $dto['data']['list']['name']);
        $this->assertSame('0', $dto['data']['list']['description']);
        $this->assertTrue(Str::isUuid($dto['meta']['trace_id']));
        $this->assertDatabaseHas('contact_lists', ['id' => $id, 'account_id' => $accountB->id]);
        $read = $this->callTool($bearer, 'lists_get', ['list_id' => $id])->assertOk()->assertJsonPath('result.structuredContent.data.list.contacts_count', 0);
        $this->assertNotSame($dto['meta']['trace_id'], $read->json('result.structuredContent.meta.trace_id'));
        $this->error($this->callTool($bearer, 'lists_get', ['list_id' => $foreign->id]), 'not_found');
        $this->assertDatabaseHas('mcp_audit_events', ['actor_id' => $user->id, 'account_id' => $accountB->id, 'operation' => 'lists_create', 'outcome' => 'succeeded', 'correlation_id' => $dto['meta']['trace_id']]);
        $this->assertDatabaseHas('audit_events', ['account_id' => $accountB->id, 'action' => 'contact_list.created']);
        $legacy = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']]], ['Authorization' => 'Bearer '.$bearer])->assertOk();
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $legacy->headers->get('MCP-Session-Id')];
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertStatus(202);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'lists_get', 'arguments' => ['list_id' => $id]]], $headers)->assertOk()->assertJsonPath('result.structuredContent.data.list.id', $id);
    }

    public function test_scope_filtering_direct_denial_and_invalid_arguments_never_mutate(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount(null, 'restricted', ['contacts.read']);
        [, , $read] = $this->mcpCredential($user, $account, ['contacts.read']);
        $this->rpc($read, 'tools/list')->assertOk()->assertJsonCount(1, 'result.tools')->assertJsonPath('result.tools.0.name', 'lists_get');
        $this->error($this->callTool($read, 'lists_create', ['list' => ['name' => 'Denied', 'description' => '']]), 'forbidden');
        $this->assertDatabaseCount('contact_lists', 0);
        foreach ([['list_id' => '1'], ['list_id' => 1.5], ['list_id' => 0], ['list_id' => 1, 'account_id' => $account->id], []] as $arguments) {
            $this->error($this->callTool($read, 'lists_get', $arguments), 'validation_failed');
        }
        $this->assertSame(6, McpAuditEvent::where('outcome', 'failed')->count());
    }

    public function test_own_connections_are_independent_of_account_selector_and_revocation_cuts_sessions(): void
    {
        $this->mcpConfiguration();
        [$user, $accountA] = $this->mcpAccount();
        [, $accountB] = $this->mcpAccount($user);
        [$token, $grant, $bearer] = $this->mcpCredential($user, $accountB);
        [$other, $otherAccount] = $this->mcpAccount();
        [, $foreign] = $this->mcpCredential($other, $otherAccount);
        DB::table('mcp_sessions')->insert(['id' => (string) Str::uuid(), 'token_id' => $token->id, 'initialized' => true, 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->withSession(['account_id' => $accountA->id])->get('/settings/connections')->assertOk()->assertSee('Synthetic agent')->assertDontSee($bearer)->assertDontSee($foreign->id);
        $this->post('/settings/connections/'.$foreign->id.'/revoke')->assertNotFound();
        $this->assertNull($foreign->fresh()->revoked_at);
        $this->post('/settings/connections/'.$grant->id.'/revoke')->assertRedirect('/settings/connections')->assertSessionHas('status', 'Conexión revocada. El cliente ya no puede utilizarla.');
        $this->get('/settings/connections')->assertOk()->assertSee('Conexión revocada. El cliente ya no puede utilizarla.');
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertDatabaseCount('mcp_sessions', 0);
        $this->rpc($bearer, 'tools/list')->assertUnauthorized();
        $this->assertDatabaseHas('mcp_audit_events', ['actor_id' => $user->id, 'account_id' => $accountB->id, 'grant_id' => $grant->id, 'operation' => 'connection.revoke', 'outcome' => 'revoked']);
    }

    public function test_cursor_survives_new_trace_but_rejects_changed_authority(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $member] = $this->mcpAccount();
        [$token, $grant] = $this->mcpCredential($user, $account);
        $first = DelegatedContext::verified($token);
        request()->attributes->set('mcp.context', $first);
        $cursor = app(RegistryCursor::class)->issue('lists_create');
        $second = DelegatedContext::verified($token);
        $this->assertNotSame($first->correlationId(), $second->correlationId());
        request()->attributes->set('mcp.context', $second);
        $this->assertSame('lists_create', app(RegistryCursor::class)->after($cursor));
        $member->update(['role' => 'read-only']);
        try {
            app(RegistryCursor::class)->after($cursor);
            $this->fail('Permission changes must reject continuation');
        } catch (ProtocolError $error) {
            $this->assertSame('Invalid cursor', $error->getMessage());
        }
        $grant->update(['revoked_at' => now()]);
        $this->expectException(ProtocolError::class);
        app(RegistryCursor::class)->after($cursor);
    }

    public function test_real_consent_denial_records_no_chosen_account_and_approval_is_attributed(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , , $client] = $this->mcpCredential($user, $account);
        $query = ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'response_type' => 'code', 'scope' => 'contacts.read contacts.write', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)];
        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertOk()->assertSee('contacts.read', false)->assertSee('Denegar');
        $pending = McpAuthorizationRequest::whereNull('consumed_at')->firstOrFail();
        $this->post('/oauth/authorize/decision', ['request_id' => $pending->id, 'decision' => 'deny'])->assertRedirect();
        $denial = McpAuditEvent::where('operation', 'oauth.authorize')->sole();
        $this->assertNull($denial->account_id);
        $this->assertNull($denial->grant_id);
        $this->assertSame('denied', $denial->outcome);
        $this->get('/oauth/authorize?'.http_build_query($query))->assertOk();
        $pending = McpAuthorizationRequest::whereNull('consumed_at')->firstOrFail();
        $this->post('/oauth/authorize/decision', ['request_id' => $pending->id, 'decision' => 'allow', 'account_id' => $account->id, 'scopes' => ['contacts.read']])->assertRedirect();
        $this->assertDatabaseHas('mcp_audit_events', ['actor_id' => $user->id, 'account_id' => $account->id, 'client_id' => $client->id, 'operation' => 'oauth.authorize', 'outcome' => 'approved']);
        $this->assertSame(['actor_id', 'account_id', 'client_id', 'grant_id', 'operation', 'outcome', 'correlation_id'], (new McpAuditEvent)->getFillable());
        $this->get('/oauth/authorize?'.http_build_query($query))->assertOk();
        $pending = McpAuthorizationRequest::whereNull('consumed_at')->firstOrFail();
        [, $foreignAccount] = $this->mcpAccount();
        $input = ['request_id' => $pending->id, 'decision' => 'allow', 'account_id' => $foreignAccount->id, 'scopes' => ['contacts.read']];
        $this->post('/oauth/authorize/decision', $input, ['Accept' => 'application/json'])->assertForbidden()->assertJsonPath('error', 'access_denied');
        $html = $this->post('/oauth/authorize/decision', $input, ['Accept' => 'text/html'])->assertOk()->assertSee('No se pudo autorizar')->assertSee('Denegar')->assertHeader('Cache-Control', 'no-store')->assertHeaderMissing('Location');
        $this->assertStringNotContainsString($pending->code_challenge, $html->getContent());
        $this->assertNull($pending->fresh()->consumed_at);
    }

    public function test_revocation_is_post_with_real_csrf_and_audit_retention_is_bounded(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, $grant, $bearer] = $this->mcpCredential($user, $account);
        $this->actingAs($user)->withSession(['account_id' => $account->id]);
        $this->get('/settings/connections/'.$grant->id.'/revoke')->assertStatus(405);
        $this->app->instance('env', 'local');
        try {
            $this->post('/settings/connections/'.$grant->id.'/revoke')->assertStatus(419);
            $this->assertNull($grant->fresh()->revoked_at);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->rpc($bearer, 'tools/list')->assertOk();
        foreach ([366, 367, 1] as $days) {
            DB::table('mcp_audit_events')->insert(['actor_id' => $user->id, 'account_id' => null, 'client_id' => $grant->client_id, 'grant_id' => null, 'operation' => 'oauth.authorize', 'outcome' => 'denied', 'correlation_id' => (string) Str::uuid(), 'created_at' => now()->subDays($days)]);
        }
        $this->artisan('mcp:prune', ['--limit' => '1'])->assertExitCode(0);
        $this->assertDatabaseCount('mcp_audit_events', 2);
        $this->assertSame(1, McpAuditEvent::where('created_at', '<', now()->subDays(365))->count());
        $this->assertSame(1, McpAuditEvent::where('created_at', '>', now()->subDays(365))->count());
    }

    public function test_form_feedback_uses_contracted_html_200_without_changing_protocol_json_errors(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , , $client] = $this->mcpCredential($user, $account);
        [, $foreign] = $this->mcpAccount();
        $foreign->update(['name' => 'Foreign unavailable workspace']);
        $this->actingAs($user);
        $get = $this->get('/oauth/authorize?client_id=unknown', ['Accept' => 'text/html']);
        $query = ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'response_type' => 'code', 'scope' => 'contacts.read', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)];
        $this->get('/oauth/authorize?'.http_build_query($query))->assertOk();
        $pending = McpAuthorizationRequest::whereNull('consumed_at')->firstOrFail();
        $input = ['request_id' => $pending->id, 'decision' => 'allow', 'account_id' => $foreign->id, 'scopes' => ['contacts.read']];
        $json = $this->post('/oauth/authorize/decision', $input, ['Accept' => 'application/json']);
        $html = $this->post('/oauth/authorize/decision', $input, ['Accept' => 'text/html']);
        $shape = fn ($response) => [$response->status(), explode(';', $response->headers->get('Content-Type'))[0]];
        $this->assertSame([[400, 'application/json'], [403, 'application/json'], [200, 'text/html']], [$shape($get), $shape($json), $shape($html)]);
        $json->assertJsonPath('error', 'access_denied');
        $html->assertSee('No se pudo autorizar')->assertDontSee('Foreign unavailable workspace')->assertHeader('Cache-Control', 'no-store')->assertHeader('Pragma', 'no-cache')->assertHeaderMissing('Location');
        $this->assertNull($pending->fresh()->consumed_at);
        $this->assertDatabaseCount('mcp_grants', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('mcp_authorization_codes', 0);
        $this->assertSame(2, McpAuditEvent::where('operation', 'oauth.authorize')->where('outcome', 'denied')->whereNull('account_id')->count());
    }

    public function test_create_outcome_distinguishes_confirmed_rollback_and_uncertain_commit(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $mode = 'rollback';
        DB::listen(function ($query) use (&$mode): void {
            if (! str_starts_with(strtolower($query->sql), 'insert') || ! str_contains($query->sql, '`contact_lists`')) {
                return;
            }
            if ($mode === 'rollback') {
                throw new \RuntimeException('Synthetic precommit observer failure');
            }
            DB::afterCommit(fn () => throw new \RuntimeException('Synthetic postcommit observer failure'));
        });
        $rollback = $this->callTool($bearer, 'lists_create', ['list' => ['name' => 'Rolled back', 'description' => '']]);
        $this->error($rollback, 'internal_error');
        $this->assertDatabaseCount('contact_lists', 0);
        $mode = 'commit';
        $response = $this->callTool($bearer, 'lists_create', ['list' => ['name' => 'Uncertain committed reply', 'description' => '']])->assertOk()->assertJsonPath('result.isError', true)->assertJsonMissingPath('result.structuredContent');
        $list = ContactList::where('account_id', $account->id)->sole();
        $this->assertSame('Uncertain committed reply', $list->name);
        $dto = json_decode($response->json('result.content.0.text'), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('internal_error', $dto['error']['code']);
        $this->assertSame('unknown', $dto['error']['outcome']);
        $this->assertFalse($dto['error']['can_repeat_operation']);
        $this->assertSame(['kind' => 'contact_list', 'id' => (string) $list->id], $dto['error']['reference']);
        $this->assertStringNotContainsString('Synthetic postcommit observer failure', $response->getContent());
    }

    public function test_unrepresentable_generated_id_rolls_back_without_returning_an_unsafe_dto(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        DB::table('contact_lists')->insert(['id' => 9007199254740991, 'account_id' => $account->id, 'name' => 'Owned upper-bound sentinel', 'description' => '', 'created_at' => now(), 'updated_at' => now()]);
        $dto = $this->error($this->callTool($bearer, 'lists_create', ['list' => ['name' => 'Cannot expose unsafe id', 'description' => '']]), 'internal_error');
        $this->assertArrayNotHasKey('reference', $dto['error']);
        $this->assertDatabaseCount('contact_lists', 1);
        $this->assertDatabaseMissing('contact_lists', ['name' => 'Cannot expose unsafe id']);
    }
}
