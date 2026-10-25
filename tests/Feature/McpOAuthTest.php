<?php

namespace Tests\Feature;

use App\Models\McpAuthorizationCode;
use App\Models\McpAuthorizationRequest;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\PersonalAccessToken;
use App\Services\Mcp\Admission;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\Mcp\OAuth\RedirectPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private function client(bool $native = false)
    {
        return app(AuthorizationServer::class)->register(['client_name' => 'Synthetic OAuth agent', 'redirect_uris' => ['http://127.0.0.1:18743/callback'], 'token_endpoint_auth_method' => 'none'], false, $native);
    }

    private function authorize($user, $account, $client, string $scope = 'contacts.read', string $verifier = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~'): array
    {
        $query = ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'response_type' => 'code', 'scope' => $scope, 'state' => 'synthetic state + exact', 'resource' => config('mcp.resource'), 'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertOk()->assertSee('Autorizar conexión');
        $pending = McpAuthorizationRequest::whereNull('consumed_at')->latest('created_at')->firstOrFail();
        $response = $this->post('/oauth/authorize/decision', ['request_id' => $pending->id, 'decision' => 'allow', 'account_id' => $account->id, 'scopes' => $scope === '' ? [] : explode(' ', $scope)]);
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->assertSame(config('mcp.issuer'), $parameters['iss']);
        $this->assertSame($query['state'], $parameters['state']);

        return ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'code' => $parameters['code'], 'redirect_uri' => $query['redirect_uri'], 'resource' => $query['resource'], 'code_verifier' => $verifier];
    }

    private function form(string $path, array|string $input, array $headers = [])
    {
        return $this->call('POST', $path, server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'] + $headers, content: is_array($input) ? http_build_query($input) : $input);
    }

    public function test_discovery_is_canonical_disabled_until_configured_and_never_uses_host(): void
    {
        $this->get('/.well-known/oauth-protected-resource/mcp')->assertNotFound()->assertHeader('Cache-Control', 'no-store');
        $this->mcpConfiguration();
        foreach (['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'] as $path) {
            $this->withHeader('Host', 'attacker.example.test')->get($path)->assertOk()->assertJsonPath('resource', 'https://emailing.example.test/mcp')->assertJsonPath('scopes_supported', [])->assertHeader('Cache-Control', 'no-store');
        }
        $this->get('/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('issuer', config('mcp.issuer'))->assertJsonPath('authorization_response_iss_parameter_supported', true)->assertJsonPath('client_id_metadata_document_supported', false)->assertJsonMissingPath('registration_endpoint')->assertJsonPath('token_endpoint_auth_methods_supported', ['none']);
        config(['mcp.issuer' => 'https://user@emailing.example.test']);
        $this->get('/.well-known/oauth-authorization-server')->assertNotFound();
    }

    public function test_pkce_hash_binding_ttl_atomic_exchange_replay_invalidation_and_new_consent(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $client = $this->client();
        $input = $this->authorize($user, $account, $client);
        $code = McpAuthorizationCode::sole();
        $grant = McpGrant::sole();
        $this->assertSame(hash('sha256', $input['code']), $code->code_hash);
        $this->assertSame(300, (int) $code->created_at->diffInSeconds($code->expires_at));
        $this->assertSame(3600, (int) $grant->created_at->diffInSeconds($grant->expires_at));
        $this->assertStringNotContainsString($input['code'], $code->toJson());
        $this->form('/oauth/token', $input + ['ignored_extension' => 'never reflected'])->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonMissingPath('refresh_token')->assertHeader('Cache-Control', 'no-store');
        $token = PersonalAccessToken::sole();
        $this->assertSame('mcp', $token->purpose);
        $this->assertSame($grant->id, $token->grant_id);
        $this->assertTrue($token->expires_at->lte($grant->expires_at));
        $context = DelegatedContext::verified($token);
        $this->form('/oauth/token', $input)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $this->assertNotNull($token->fresh()->revoked_at);
        try {
            $context->authorize();
            $this->fail('Code replay did not revoke derived token.');
        } catch (HttpException $error) {
            $this->assertSame(401, $error->getStatusCode());
        }
        $second = $this->authorize($user, $account, $client);
        $this->form('/oauth/token', $second)->assertOk();
        $this->assertDatabaseCount('mcp_grants', 2);
        $this->assertSame($grant->expires_at->timestamp, $grant->fresh()->expires_at->timestamp);
        $this->form('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->id, 'refresh_token' => 'synthetic-unsupported'])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
    }

    public function test_invalid_bindings_attempt_budget_expiration_and_membership_are_fail_closed(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $member] = $this->mcpAccount();
        $client = $this->client();
        $input = $this->authorize($user, $account, $client);
        $other = $this->client();
        foreach ([['client_id' => $other->id], ['redirect_uri' => 'http://127.0.0.1:18743/other'], ['resource' => 'https://other.example.test/mcp']] as $change) {
            $this->form('/oauth/token', array_replace($input, $change))->assertStatus(400);
        }
        $this->assertNull(McpAuthorizationCode::sole()->consumed_at);
        for ($i = 0; $i < 5; $i++) {
            $this->form('/oauth/token', array_replace($input, ['code_verifier' => str_repeat('x', 43)]))->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        }
        $this->assertSame(5, McpAuthorizationCode::sole()->verifier_attempts);
        $this->form('/oauth/token', $input)->assertStatus(400);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $fresh = $this->authorize($user, $account, $client);
        $code = McpAuthorizationCode::where('code_hash', hash('sha256', $fresh['code']))->sole();
        $code->update(['expires_at' => now()->subSecond()]);
        $this->form('/oauth/token', $fresh)->assertStatus(400);
        $fresh = $this->authorize($user, $account, $client);
        $member->update(['role' => 'restricted', 'scopes' => []]);
        $this->form('/oauth/token', $fresh)->assertStatus(400);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_unsafe_redirects_duplicates_empty_values_and_public_authentication_policy(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $client = $this->client();
        foreach (['client_id=missing&redirect_uri=https%3A%2F%2Fattacker.example.test', 'client_id='.$client->id.'&redirect_uri=https%3A%2F%2Fattacker.example.test', 'client_id='.$client->id.'&client%5Fid='.$client->id] as $query) {
            $this->get('/oauth/authorize?'.$query)->assertStatus(400)->assertHeaderMissing('Location');
        }
        $this->assertDatabaseCount('mcp_authorization_requests', 0);
        $input = $this->authorize($user, $account, $client);
        foreach (['client_secret', 'client_assertion', 'client_assertion_type'] as $field) {
            $this->form('/oauth/token', $input + [$field => 'unsupported'])->assertStatus(400)->assertJsonPath('error', 'invalid_client');
        }
        foreach (['/oauth/token', '/oauth/revoke'] as $endpoint) {
            $this->form($endpoint, $input, ['HTTP_AUTHORIZATION' => 'Basic c3ludGhldGlj'])->assertStatus(401)->assertExactJson(['error' => 'invalid_client'])->assertHeader('WWW-Authenticate', 'Basic realm="MCP OAuth"')->assertHeader('Cache-Control', 'no-store');
        }
        $this->form('/oauth/token', http_build_query($input).'&client%5Fid='.$client->id)->assertStatus(400)->assertJsonPath('error', 'invalid_request');
        $this->form('/oauth/token', $input + ['client_secret' => '', 'unknown' => 'ignored'])->assertOk()->assertJsonMissingPath('unknown');
        $this->postJson('/oauth/token', $input)->assertStatus(415);
        $this->form('/oauth/token', str_repeat('x', 16385))->assertStatus(400);
    }

    public function test_controlled_registration_exact_native_redirects_and_no_remote_metadata_fetch(): void
    {
        $this->mcpConfiguration();
        Http::preventStrayRequests();
        $registration = ['client_name' => 'Synthetic dynamic agent', 'redirect_uris' => ['https://agent.example.test/callback'], 'token_endpoint_auth_method' => 'none'];
        $this->postJson('/oauth/register', $registration)->assertNotFound();
        config(['mcp.dynamic_registration' => true, 'mcp.registration_https_hosts' => ['agent.example.test']]);
        $this->postJson('/oauth/register', array_diff_key($registration, ['token_endpoint_auth_method' => 1]))->assertStatus(400)->assertJsonPath('error', 'invalid_client_metadata');
        $this->postJson('/oauth/register', array_replace($registration, ['redirect_uris' => ['https://other.example.test/callback']]))->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
        $this->postJson('/oauth/register', $registration + ['client_uri' => 'http://169.254.169.254/latest'])->assertCreated()->assertJsonMissingPath('client_uri')->assertJsonMissingPath('client_secret');
        $client = $this->client(true);
        $policy = app(RedirectPolicy::class);
        $this->assertTrue($policy->accepts($client, 'http://127.0.0.1:19333/callback'));
        foreach (['http://localhost:19333/callback', 'http://127.0.0.1:19333/other', 'http://127.0.0.1:19333/callback?other=1', 'http://127.0.0.1:19333/callback#fragment'] as $uri) {
            $this->assertFalse($policy->accepts($client, $uri));
        }
        Http::assertNothingSent();
    }

    public function test_token_issuance_rollback_and_revocation_are_atomic_and_owned(): void
    {
        $this->mcpConfiguration();
        config(['app.debug' => true]);
        [$user, $account] = $this->mcpAccount();
        $client = $this->client();
        $input = $this->authorize($user, $account, $client);
        PersonalAccessToken::creating(fn () => throw new \RuntimeException('Synthetic persistence failure'));
        try {
            $this->form('/oauth/token', $input)->assertStatus(503)->assertExactJson(['error' => 'temporarily_unavailable']);
        } finally {
            PersonalAccessToken::flushEventListeners();
        }
        $this->assertNull(McpAuthorizationCode::sole()->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $token = $this->form('/oauth/token', $input)->assertOk()->json('access_token');
        $other = $this->client();
        $this->form('/oauth/revoke', ['client_id' => $other->id, 'token' => $token, 'token_type_hint' => 'unknown'])->assertOk();
        $this->assertNull(PersonalAccessToken::sole()->revoked_at);
        $this->form('/oauth/revoke', ['client_id' => $client->id, 'token' => $token, 'token_type_hint' => 'unknown'])->assertOk();
        $this->assertNotNull(PersonalAccessToken::sole()->revoked_at);
        $this->assertNotNull(McpGrant::sole()->revoked_at);
        $this->form('/oauth/revoke', ['client_id' => $client->id, 'token' => 'unknown'])->assertOk();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_empty_optional_values_and_connection_only_scope_use_a_real_browser_owned_pending_request(): void
    {
        $this->mcpConfiguration();
        config(['app.url' => 'https://emailing.example.test']);
        [$user, $account] = $this->mcpAccount();
        $client = $this->client();
        $query = ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'response_type' => 'code', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43), 'scope' => '', 'state' => '', 'unknown_extension' => 'https://attacker.example.test/never-fetch'];
        $this->get('/oauth/authorize?'.http_build_query($query))->assertRedirect('https://emailing.example.test/login');
        $pending = McpAuthorizationRequest::sole();
        $this->assertNull($pending->state);
        $this->assertSame([], $pending->scopes);
        $this->assertStringNotContainsString('unknown_extension', $pending->toJson());
        $this->assertStringContainsString($pending->id, session('url.intended'));
        $this->actingAs($user)->get('/oauth/authorize?pending='.$pending->id)->assertOk()->assertSee('Sólo información básica');
        $this->assertSame($user->id, (int) $pending->fresh()->user_id);
        $response = $this->post('/oauth/authorize/decision', ['request_id' => $pending->id, 'decision' => 'deny']);
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->assertSame(['error' => 'access_denied', 'iss' => config('mcp.issuer')], $parameters);
        $this->assertDatabaseCount('mcp_grants', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->get('/oauth/authorize?pending='.$pending->id)->assertStatus(400);
        $this->withSession(['mcp.browser' => 'foreign-browser-binding'])->get('/oauth/authorize?pending='.$pending->id)->assertStatus(400);
    }

    public function test_sql_http_rate_dimensions_and_pending_authorization_cap_return_retry_after(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $client = $this->client();
        $query = ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'response_type' => 'code', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)];
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertOk();
        }
        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(5, McpAuthorizationRequest::whereNotNull('user_id')->count());
        $this->assertDatabaseCount('mcp_authorization_requests', 5);
        $input = ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'code' => str_repeat('c', 64), 'code_verifier' => str_repeat('v', 64)];
        // A fixed UTC window may roll during this loop; at most two adjacent windows
        // fit its bounded duration, and each admits only 30. Do not assume wall-clock phase.
        $limited = false;
        for ($i = 0; $i < 61; $i++) {
            $attempt = $this->form('/oauth/token', $input);
            if ($attempt->getStatusCode() === 429) {
                $limited = true;
                break;
            }
            $attempt->assertStatus(400);
        }
        $this->assertTrue($limited);
        $before = DB::table('mcp_admission_windows')->orderBy('key')->get()->toJson();
        $response = $this->form('/oauth/token', $input)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGreaterThanOrEqual(1, (int) $response->headers->get('Retry-After'));
        $this->assertSame($before, DB::table('mcp_admission_windows')->orderBy('key')->get()->toJson());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_bounded_retention_preserves_referenced_grant_tombstones_and_unrelated_active_data(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$token, $grant] = $this->mcpCredential($user, $account);
        [$active, $activeGrant] = $this->mcpCredential($user, $account);
        DB::table('mcp_grants')->where('id', $grant->id)->update(['expires_at' => now()->subDays(100), 'revoked_at' => now()->subDays(95)]);
        DB::table('personal_access_tokens')->where('id', $token->id)->update(['expires_at' => now()->subDays(40), 'revoked_at' => now()->subDays(35)]);
        DB::getSchemaBuilder()->create('mcp_retention_reference_fixture', function (Blueprint $table): void {
            $table->uuid('grant_id');
            $table->foreign('grant_id')->references('id')->on('mcp_grants')->restrictOnDelete();
        });
        try {
            DB::table('mcp_retention_reference_fixture')->insert(['grant_id' => $grant->id]);
            $this->artisan('mcp:prune', ['--limit' => '1'])->assertSuccessful();
            $this->assertNotNull($grant->fresh());
            $this->assertNull($token->fresh());
            $this->assertNull($active->fresh()->revoked_at);
            $this->assertNull($activeGrant->fresh()->revoked_at);
            DB::table('mcp_retention_reference_fixture')->delete();
            $this->artisan('mcp:prune', ['--limit' => '1'])->assertSuccessful();
            $this->assertNull($grant->fresh());
            $this->artisan('mcp:prune', ['--limit' => '1001'])->assertExitCode(2);
        } finally {
            DB::getSchemaBuilder()->dropIfExists('mcp_retention_reference_fixture');
        }
    }

    public function test_production_quota_thresholds_and_unsupported_authentication_use_real_http_and_sql(): void
    {
        $this->mcpConfiguration();
        config(['mcp.dynamic_registration' => true, 'mcp.registration_https_hosts' => ['agent.example.test']]);
        $client = $this->client();
        $registration = ['client_name' => 'Synthetic limited agent', 'redirect_uris' => ['https://agent.example.test/callback'], 'token_endpoint_auth_method' => 'none'];
        $this->postJson('/oauth/register', $registration, ['Authorization' => 'Bearer unsupported-synthetic-auth'])->assertStatus(400)->assertJsonPath('error', 'invalid_client');
        $this->get('/oauth/authorize', ['Authorization' => 'Basic unsupported-synthetic-auth'])->assertStatus(400)->assertJsonPath('error', 'invalid_client');
        $prime = function (string $purpose, string $subject, int $hits, int $seconds): string {
            $now = (int) DB::selectOne('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP()) AS quota_now')->quota_now;
            $end = (intdiv($now, $seconds) + 1) * $seconds;
            $key = hash('sha256', $purpose.':'.$subject.':'.$end);
            DB::table('mcp_admission_windows')->updateOrInsert(['key' => $key], ['hits' => $hits, 'expires_at' => gmdate('Y-m-d H:i:s', $end)]);

            return $key;
        };
        $input = ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'code' => str_repeat('c', 64), 'code_verifier' => str_repeat('v', 64)];
        $clientWindow = $prime('token.client', $client->id, 59, 60);
        $this->form('/oauth/token', $input, ['REMOTE_ADDR' => '192.0.2.1'])->assertStatus(400);
        $this->form('/oauth/token', $input, ['REMOTE_ADDR' => '192.0.2.2'])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(60, DB::table('mcp_admission_windows')->where('key', $clientWindow)->value('hits'));
        $admission = app(Admission::class);
        $revokeWindow = $prime('revoke.ip', $admission->peer('192.0.2.3'), 59, 60);
        $this->form('/oauth/revoke', ['client_id' => $client->id, 'token' => 'unknown'], ['REMOTE_ADDR' => '192.0.2.3'])->assertOk();
        $this->form('/oauth/revoke', ['client_id' => $client->id, 'token' => 'unknown'], ['REMOTE_ADDR' => '192.0.2.3'])->assertStatus(429);
        $this->assertSame(60, DB::table('mcp_admission_windows')->where('key', $revokeWindow)->value('hits'));
        $json = fn (string $ip) => $this->call('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip], content: json_encode($registration, JSON_THROW_ON_ERROR));
        $dayWindow = $prime('register.day', $admission->peer('192.0.2.4'), 19, 86400);
        $json('192.0.2.4')->assertCreated();
        $json('192.0.2.4')->assertStatus(429);
        $this->assertSame(20, DB::table('mcp_admission_windows')->where('key', $dayWindow)->value('hits'));
        $minuteWindow = $prime('register.ip', $admission->peer('192.0.2.5'), 4, 60);
        $json('192.0.2.5')->assertCreated();
        $json('192.0.2.5')->assertStatus(429);
        $this->assertSame(5, DB::table('mcp_admission_windows')->where('key', $minuteWindow)->value('hits'));
        $rows = [];
        for ($i = 0; $i < 98; $i++) {
            $rows[] = ['id' => (string) Str::uuid(), 'issuer' => config('mcp.issuer'), 'name' => 'Synthetic registry capacity', 'redirect_uris' => json_encode($registration['redirect_uris']), 'dynamic' => true, 'native_port_relaxation' => false, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('mcp_clients')->insert($rows);
        $json('192.0.2.6')->assertStatus(429);
        $this->assertSame(100, McpClient::where('dynamic', true)->count());
    }
}
