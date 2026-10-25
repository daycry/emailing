<?php

namespace Tests\Feature;

use App\Models\McpAuthorizationRequest;
use App\Models\McpClient;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\Mcp\OAuth\Input;
use App\Services\Mcp\OAuth\OAuthError;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpOAuthRegistrationRegressionTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private int $peer = 10;

    private function configureRegistration(): void
    {
        $this->mcpConfiguration();
        config(['mcp.dynamic_registration' => true, 'mcp.registration_https_hosts' => ['agent.example.test']]);
        Http::preventStrayRequests();
    }

    private function metadata(string $redirect, mixed $name = 'Synthetic registration agent'): array
    {
        return ['client_name' => $name, 'redirect_uris' => [$redirect], 'token_endpoint_auth_method' => 'none'];
    }

    private function registerHttp(array $metadata): TestResponse
    {
        // Independent reserved test peers exercise validation without exhausting
        // the real five-per-minute admission limit for a single peer.
        return $this->call('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '192.0.2.'.$this->peer++], content: json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_reserved_response_query_keys_are_rejected_by_http_and_local_registration_even_when_empty(): void
    {
        $this->configureRegistration();
        foreach (['code', 'state', 'iss', 'error', 'error_description'] as $key) {
            $encoded = '%'.bin2hex($key[0]).substr($key, 1);
            foreach ([$key, $key.'=', $key.'=occupied', $encoded.'='] as $query) {
                $redirect = 'https://agent.example.test/callback?'.$query;
                $this->registerHttp($this->metadata($redirect))->assertStatus(400)->assertExactJson(['error' => 'invalid_redirect_uri'])->assertHeaderMissing('Location');
                $this->artisan('mcp:client-register', ['name' => 'Synthetic local registration', '--redirect' => [$redirect]])->expectsOutputToContain('invalid_redirect_uri')->assertExitCode(1);
                $this->assertDatabaseCount('mcp_clients', 0);
            }
        }
        Http::assertNothingSent();
    }

    public function test_duplicate_decoded_keys_stay_rejected_and_nonreserved_empty_queries_survive_real_authorization(): void
    {
        $this->configureRegistration();
        foreach (['tracking=a&track%69ng=b', 'tracking=&tracking=x', 'tracking=%zz', 'tracking=%FF'] as $query) {
            $metadata = $this->metadata('https://agent.example.test/callback?'.$query);
            $this->registerHttp($metadata)->assertStatus(400)->assertExactJson(['error' => 'invalid_request']);
            try {
                app(AuthorizationServer::class)->register($metadata, false);
                $this->fail('Invalid redirect query was accepted by local registration.');
            } catch (OAuthError $error) {
                $this->assertSame('invalid_request', $error->error);
            }
        }
        $this->assertDatabaseCount('mcp_clients', 0);
        // OAuth form/query semantics keep treating empty optional values as omitted.
        $this->assertSame(['extension' => 'ok'], Input::parameters('scope=&state=&extension=ok'));
        [$user, $account] = $this->mcpAccount();
        $redirect = 'https://agent.example.test/callback?tracking=&fixed=one';
        $created = $this->registerHttp($this->metadata($redirect))->assertCreated()->assertJsonPath('redirect_uris', [$redirect]);
        $client = McpClient::findOrFail($created->json('client_id'));
        foreach (['allow', 'deny'] as $decision) {
            $query = ['client_id' => $client->id, 'redirect_uri' => $redirect, 'resource' => config('mcp.resource'), 'response_type' => 'code', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43), 'scope' => 'contacts.read', 'state' => 'synthetic-state'];
            $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($query))->assertOk();
            $pending = McpAuthorizationRequest::whereNull('consumed_at')->sole();
            $response = $this->post('/oauth/authorize/decision', ['request_id' => $pending->id, 'decision' => $decision, 'account_id' => $account->id, 'scopes' => ['contacts.read']])->assertRedirect();
            $returned = Input::parameters(parse_url($response->headers->get('Location'), PHP_URL_QUERY));
            $this->assertStringStartsWith($redirect.'&', $response->headers->get('Location'));
            $this->assertSame('one', $returned['fixed']);
            $this->assertSame('synthetic-state', $returned['state']);
            $this->assertSame(config('mcp.issuer'), $returned['iss']);
            $this->assertArrayHasKey($decision === 'allow' ? 'code' : 'error', $returned);
            if ($decision === 'deny') {
                $this->assertSame('access_denied', $returned['error']);
            }
        }
        Http::assertNothingSent();
    }

    public function test_unicode_client_names_up_to_150_characters_are_persisted_exactly_by_http_and_cli(): void
    {
        $this->configureRegistration();
        $redirect = 'https://agent.example.test/callback';
        foreach ([str_repeat('á', 100), str_repeat('é', 150), str_repeat('😀', 150), str_repeat('a', 150)] as $name) {
            $before = McpClient::count();
            $created = $this->registerHttp($this->metadata($redirect, $name))->assertCreated()->assertJsonPath('client_name', $name);
            $this->assertSame($name, McpClient::findOrFail($created->json('client_id'))->name);
            $this->artisan('mcp:client-register', ['name' => $name, '--redirect' => [$redirect]])->assertSuccessful();
            $this->assertSame($before + 2, McpClient::count());
            $this->assertContains($name, McpClient::where('dynamic', false)->pluck('name')->all());
        }
        $this->assertDatabaseCount('mcp_clients', 8);
        Http::assertNothingSent();
    }

    public function test_oversized_invalid_unicode_and_nonstrings_do_not_create_clients(): void
    {
        $this->configureRegistration();
        $redirect = 'https://agent.example.test/callback';
        foreach ([str_repeat('a', 151), str_repeat('á', 151), str_repeat('😀', 151), '', "Synthetic\nname", null, 123, []] as $name) {
            $this->registerHttp($this->metadata($redirect, $name))->assertStatus(400);
            try {
                app(AuthorizationServer::class)->register($this->metadata($redirect, $name), false);
                $this->fail('Invalid client name was accepted by local registration.');
            } catch (OAuthError $error) {
                $this->assertSame(400, $error->status);
            }
        }
        $this->artisan('mcp:client-register', ['name' => str_repeat('😀', 151), '--redirect' => [$redirect]])->assertExitCode(1);
        try {
            app(AuthorizationServer::class)->register($this->metadata($redirect, "invalid\xff"), false);
            $this->fail('Malformed UTF-8 was accepted by local registration.');
        } catch (OAuthError $error) {
            $this->assertSame(400, $error->status);
        }
        $this->call('POST', '/oauth/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '192.0.2.200'], content: '{"client_name":"invalid'."\xff".'","redirect_uris":["https://agent.example.test/callback"],"token_endpoint_auth_method":"none"}')->assertStatus(400);
        $this->assertDatabaseCount('mcp_clients', 0);
        Http::assertNothingSent();
    }
}
