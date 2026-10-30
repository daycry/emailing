<?php

namespace Tests\Feature;

use App\Models\AccountApiKey;
use App\Models\ExternalIdentity;
use App\Models\MailSender;
use App\Services\AccountKeyService;
use App\Services\Mcp\GrantService;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

/** Private candidate: not installed or functionally executed. Not an actual server restart/restore. */
class McpPersistenceTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private array $accounts = [];

    protected function tearDown(): void
    {
        // Remove only this test's accepted synthetic sources before migration down.
        if ($this->accounts !== []) {
            DB::table('api_mail_requests')->whereIn('account_id', $this->accounts)->delete();
        }
        parent::tearDown();
    }

    public function test_kernel_rebootstrap_keeps_identity_key_and_live_revocation(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $identity = ExternalIdentity::create(['user_id' => $user->id, 'provider' => 'google',
            'issuer' => 'https://accounts.google.com', 'subject' => 'synthetic-persistence-subject']);
        $identityDigest = hash('sha256', json_encode($identity->getAttributes(), JSON_THROW_ON_ERROR));
        [, $grant, $bearer] = $this->mcpCredential($user, $account, ['contacts.read']);
        $key = config('app.key');
        $this->connection($bearer)->assertOk()->assertJsonPath('result.structuredContent.data.account.id', $account->id);

        $this->refreshApplication();
        $this->mcpConfiguration();
        // Never put an encryption key into PHPUnit's expected/actual diagnostics.
        $this->assertTrue(hash_equals($key, config('app.key')));
        $this->assertSame($identityDigest, hash('sha256', json_encode($identity->fresh()->getAttributes(), JSON_THROW_ON_ERROR)));
        $this->connection($bearer)->assertOk()
            ->assertJsonPath('result.structuredContent.data.connection.public_id', $grant->id)
            ->assertJsonPath('result.structuredContent.data.principal.user_id', $user->id);
        app(GrantService::class)->revoke($grant->id, $user->id);
        $this->refreshApplication();
        $this->mcpConfiguration();
        $this->connection($bearer)->assertUnauthorized();
        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    public function test_restore_command_is_read_only_by_default(): void
    {
        $this->fixture();
        $before = $this->state();
        $this->artisan('mcp:invalidate-restored-access')->assertExitCode(0);
        $this->assertSame($before, $this->state());
    }

    public function test_restore_command_refuses_apply_without_maintenance_before_any_mutation(): void
    {
        $this->fixture();
        $before = $this->state();
        $this->maintenance(false);
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])
            ->expectsOutput('maintenance_mode_required')->assertExitCode(1);
        $this->assertSame($before, $this->state());
    }

    public function test_restored_access_can_be_invalidated_while_mcp_admission_is_disabled(): void
    {
        [, , $token, $grant, , $client] = $this->fixture();
        $this->maintenance(true);
        config(['mcp.enabled' => false]);
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])->assertExitCode(0);
        foreach ([$token, $grant, $client] as $model) {
            $this->assertNotNull($model->fresh()->revoked_at);
        }
        $this->assertDatabaseCount('mcp_sessions', 0);
    }

    public function test_invalidation_preserves_accepted_mcp_receipts_outbox_and_provenance(): void
    {
        [$user, $account] = $this->fixture();
        [, $grant, $bearer, $client] = $this->mcpCredential($user, $account, ['mail.send']);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test',
            'name' => 'Synthetic restore sender', 'lab_authorized' => true]);
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'mail_send', 'arguments' => ['idempotency_key' => 'synthetic-restore-history-0001',
                'message' => ['sender_id' => $sender->id, 'recipients' => [['email' => 'recipient@example.test']],
                    'content' => ['type' => 'inline', 'subject' => 'Synthetic restore', 'text' => 'Synthetic body']]],
                '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => (object) []]]],
            ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'mail_send'])
            ->assertOk()->assertJsonPath('result.isError', false);
        $id = $response->json('result.structuredContent.data.request.request_id');
        $this->assertIsString($id);
        $request = DB::table('api_mail_requests')->where('id', $id)->sole();
        $this->assertSame('mcp', $request->origin);
        $this->assertSame($grant->id, $request->mcp_grant_id);
        $this->assertSame($client->id, $request->mcp_client_id);
        $this->assertDatabaseCount('api_mail_requests', 1);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_outbox', 1);
        $tables = ['api_mail_requests', 'api_mail_recipients', 'deliveries', 'delivery_outbox', 'mcp_audit_events'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }
        $this->maintenance(true);
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])->assertExitCode(0);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], hash('sha256', DB::table($table)->orderBy('id')->get()->toJson()));
        }
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertNotNull($client->fresh()->revoked_at);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_old_snapshot_invalidation_closes_all_mcp_access_without_deleting_history_or_account_keys(): void
    {
        [$user, $account, $token, $grant, $bearer, $client] = $this->fixture();
        [$other, $foreign] = $this->mcpAccount();
        [$otherToken, $otherGrant, , $otherClient] = $this->mcpCredential($other, $foreign);
        $key = DB::transaction(fn () => app(AccountKeyService::class)->create(
            $account->id, ['name' => 'Synthetic restore key', 'scopes' => ['mail.send']], ['mail.send'], actor: $user->id));
        $apiKey = AccountApiKey::where('public_id', $key['key']['id'])->sole();
        $apiBefore = $apiKey->toArray();
        $rowCounts = ['grants' => DB::table('mcp_grants')->count(), 'tokens' => DB::table('personal_access_tokens')->count()];
        $this->maintenance(true);
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])->assertExitCode(0);
        foreach ([$token, $grant, $client, $otherToken, $otherGrant, $otherClient] as $model) {
            $this->assertNotNull($model->fresh()->revoked_at);
        }
        $this->assertSame($rowCounts['grants'], DB::table('mcp_grants')->count());
        $this->assertSame($rowCounts['tokens'], DB::table('personal_access_tokens')->count());
        $this->assertSame($apiBefore, $apiKey->fresh()->toArray());
        $this->assertDatabaseCount('mcp_sessions', 0);
        $this->assertNotNull(DB::table('mcp_authorization_codes')->sole()->consumed_at);
        $this->assertNotNull(DB::table('mcp_authorization_requests')->sole()->consumed_at);
        $this->assertSame($user->id, $user->fresh()->id);
        $this->assertSame($account->id, $account->fresh()->id);
        $this->maintenance(false);
        $this->connection($bearer)->assertUnauthorized();
    }

    public function test_repeated_invalidation_preserves_existing_revocation_timestamps(): void
    {
        $this->fixture();
        $this->maintenance(true);
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])->assertExitCode(0);
        $before = $this->state();
        $this->travel(3)->seconds();
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($before, $this->state());
        $this->travelBack();
    }

    public function test_failure_late_in_the_transaction_rolls_back_every_revocation_and_session_delete(): void
    {
        $this->fixture();
        $before = $this->state();
        $this->maintenance(true);
        $inject = true;
        DB::listen(function (QueryExecuted $event) use (&$inject): void {
            if ($inject && str_starts_with(strtolower($event->sql), 'delete from')
                && str_contains($event->sql, 'mcp_sessions')) {
                $inject = false;
                throw new \RuntimeException('synthetic_private_database_failure');
            }
        });
        $this->artisan('mcp:invalidate-restored-access', ['--apply' => true])
            ->expectsOutput('restore_access_invalidation_failed')->assertExitCode(1);
        $this->assertFalse($inject);
        $this->assertSame($before, $this->state());
        $this->assertSame(0, DB::transactionLevel());
    }

    private function fixture(): array
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $this->accounts[] = $account->id;
        [$token, $grant, $bearer, $client] = $this->mcpCredential($user, $account, ['contacts.read']);
        $redirect = $client->redirect_uris[0];
        $code = DB::table('mcp_authorization_codes')->insertGetId([
            'code_hash' => hash('sha256', Str::random(64)), 'grant_id' => $grant->id,
            'client_id' => $client->id, 'redirect_uri' => $redirect, 'resource' => config('mcp.resource'),
            'code_challenge' => str_repeat('a', 43), 'expires_at' => now()->addMinutes(5),
            'created_at' => now(), 'updated_at' => now()]);
        $token->forceFill(['authorization_code_id' => $code])->save();
        DB::table('mcp_authorization_requests')->insert([
            'id' => (string) Str::uuid(), 'browser_hash' => hash('sha256', Str::random(64)),
            'user_id' => $user->id, 'client_id' => $client->id, 'redirect_uri' => $redirect,
            'resource' => config('mcp.resource'), 'scopes' => json_encode(['contacts.read'], JSON_THROW_ON_ERROR),
            'code_challenge' => str_repeat('a', 43), 'expires_at' => now()->addMinutes(10),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('mcp_sessions')->insert(['id' => (string) Str::uuid(), 'token_id' => $token->id,
            'initialized' => true, 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $account, $token, $grant, $bearer, $client];
    }

    private function state(): array
    {
        $state = [];
        foreach (['mcp_clients', 'mcp_grants', 'personal_access_tokens', 'mcp_authorization_codes',
            'mcp_authorization_requests', 'mcp_sessions'] as $table) {
            // Credentials never enter PHPUnit's expected/actual diagnostics.
            $state[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
        }

        return $state;
    }

    private function maintenance(bool $active): void
    {
        $this->mock(MaintenanceMode::class, fn ($mock) => $mock->shouldReceive('active')->andReturn($active));
    }

    private function connection(string $bearer): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'connection_info', 'arguments' => (object) [], '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) []]]],
            ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'connection_info']);
    }
}
