<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Services\AccountKeyService;
use App\Services\Mcp\Concurrency;
use App\Services\Mcp\GrantService;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\ToolError;
use App\Services\Mcp\TransportServer;
use App\Services\Mcp\WireJson;
use App\Support\StrictMcpJson;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpTransportTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private function packet(string $method = 'server/discover', array $params = [], int $id = 1): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) array_merge($params, ['_meta' => (object) ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]])];
    }

    private function rpc(string $bearer, array $packet, array $headers = [])
    {
        return $this->postJson('/mcp', $packet, array_merge(['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $packet['method']], $headers));
    }

    public function test_real_sdk_modern_discovery_legacy_initialization_and_live_revocation(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$token, $grant, $bearer] = $this->mcpCredential($user, $account);
        $this->rpc($bearer, $this->packet())->assertOk()->assertJsonPath('result.supportedVersions', ['2025-11-25', '2026-07-28'])->assertHeaderMissing('MCP-Session-Id');
        $this->rpc($bearer, $this->packet('tools/list'))->assertOk()->assertJsonCount(2, 'result.tools');
        $init = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']]], ['Authorization' => 'Bearer '.$bearer]);
        $init->assertOk()->assertJsonPath('result.protocolVersion', '2025-11-25');
        $session = $init->headers->get('MCP-Session-Id');
        $this->assertNotEmpty($session);
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $session];
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertStatus(202);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'], $headers)->assertOk();
        app(GrantService::class)->revoke($grant->id, $user->id);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list'], $headers)->assertUnauthorized();
        $this->rpc($bearer, $this->packet())->assertUnauthorized();
    }

    public function test_no_stateful_fallback_and_wrong_purpose_resource_expiry_or_membership_are_denied(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $member] = $this->mcpAccount();
        [$token, $grant, $bearer] = $this->mcpCredential($user, $account);
        $this->actingAs($user)->postJson('/mcp', $this->packet())->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="https://emailing.example.test/.well-known/oauth-protected-resource"');
        $keys = app(AccountKeyService::class);
        $accountKey = $keys->create($account->id, ['name' => 'Synthetic account API credential', 'scopes' => ['mail.send']], ['mail.send'], actor: $user->id);
        $candidate = $keys->candidate(\Illuminate\Http\Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$accountKey['secret']]));
        $this->assertSame($account->id, $keys->operation($candidate, ['mail.send'], fn ($key) => $key->account_id));
        $this->rpc($accountKey['secret'], $this->packet())->assertUnauthorized();
        $this->assertSame($account->id, $keys->operation($candidate, ['mail.send'], fn ($key) => $key->account_id));
        foreach (['Google.synthetic', 'synthetic-account-api-key', '1|invalid'] as $invalid) {
            $this->rpc($invalid, $this->packet())->assertUnauthorized();
        }
        foreach (['purpose' => 'api', 'resource' => 'https://other.example.test/mcp', 'expires_at' => now()->subSecond(), 'revoked_at' => now()] as $field => $value) {
            $old = $token->{$field};
            $token->forceFill([$field => $value])->save();
            $this->rpc($bearer, $this->packet())->assertUnauthorized();
            $token->forceFill([$field => $old])->save();
        }
        $member->update(['role' => 'unknown']);
        $this->rpc($bearer, $this->packet('tools/list'))->assertUnauthorized();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_headers_origins_versions_and_protocol_errors_are_bounded_nonreflecting(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->rpc($bearer, $this->packet(), ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
        $this->rpc($bearer, $this->packet(), ['Mcp-Method' => 'mismatch'])->assertStatus(400)->assertJsonPath('error.code', -32020);
        $packet = $this->packet();
        $packet['params']->_meta->{'io.modelcontextprotocol/protocolVersion'} = 'future';
        $this->rpc($bearer, $packet)->assertStatus(400)->assertJsonPath('error.code', -32022)->assertJsonPath('error.data.supported', ['2025-11-25', '2026-07-28']);
        $this->rpc($bearer, $this->packet('unknown-sensitive-value'))->assertNotFound()->assertDontSee('unknown-sensitive-value');
        $this->rpc($bearer, $this->packet('tools/call', ['name' => 'unknown-sensitive-value', 'arguments' => (object) []]), ['Mcp-Name' => '=?base64?'.base64_encode('unknown-sensitive-value').'?='])->assertStatus(400)->assertDontSee('unknown-sensitive-value');
        $this->getJson('/mcp', ['Authorization' => 'Bearer '.$bearer])->assertStatus(405)->assertHeader('Allow', 'POST');
        $this->rpc($bearer, $this->packet('tools/list', ['per_page' => 101]))->assertStatus(400);
    }

    public function test_raw_guard_precedes_framework_decoding_and_preserves_exact_integer_and_string_semantics(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        foreach (['{"jsonrpc":"2.0","method":"ping","method":"ping"}', '{"x":1,"\\u0078":2}', str_repeat('[', 33).'0'.str_repeat(']', 33), '{"x":"'.str_repeat('a', 4194304).'"}', '{"x":NaN}'] as $raw) {
            $response = $this->call('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$bearer], content: $raw);
            $this->assertContains($response->getStatusCode(), [400, 413]);
        }
        $ast = StrictMcpJson::decode('{"id":1e0,"name":"  preserve  ","empty":"","ratio":1.25,"items":[{"id":9007199254740991.0}]}');
        $ast = WireJson::integerInputs($ast, ['properties' => ['id' => ['type' => 'integer'], 'items' => ['items' => ['properties' => ['id' => ['type' => 'integer']]]]]], []);
        $this->assertSame('{"id":1,"name":"  preserve  ","empty":"","ratio":1.25,"items":[{"id":9007199254740991}]}', WireJson::encode($ast));
        foreach (['1.0000000000000001', '9007199254740992', '1e999999'] as $number) {
            try {
                WireJson::integerInputs(StrictMcpJson::decode($number), ['type' => 'integer'], []);
                $this->fail('Non-exact integer accepted');
            } catch (ProtocolError $error) {
                $this->assertSame(-32602, $error->rpcCode);
            }
        }
    }

    public function test_sql_concurrency_slots_reject_excess_and_release_after_exception(): void
    {
        $this->mcpConfiguration();
        [, $account] = $this->mcpAccount();
        config(['mcp.concurrency_per_account' => 1]);
        $slots = app(Concurrency::class);
        $slots->run($account->id, function () use ($slots, $account): void {
            try {
                $slots->run($account->id, fn () => $this->fail('Concurrent slot bypassed'));
                $this->fail('No overload rejection');
            } catch (ProtocolError $error) {
                $this->assertSame(429, $error->status);
            }
        });
        $this->assertTrue($slots->run($account->id, fn () => true));
    }

    public function test_transport_ip_admission_precedes_raw_parse_origin_and_authentication_rejection(): void
    {
        $this->mcpConfiguration();
        foreach (['{', '{"x":1,"x":2}', str_repeat('[', 33).'0'.str_repeat(']', 33)] as $raw) {
            $this->call('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: $raw)->assertStatus(400);
        }
        $this->postJson('/mcp', $this->packet(), ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
        $this->postJson('/mcp', $this->packet())->assertUnauthorized();
        $this->assertSame(5, (int) DB::table('mcp_admission_windows')->sum('hits'));
        DB::table('mcp_admission_windows')->update(['hits' => 60]);
        $this->call('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json'], content: '{')->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(60, (int) DB::table('mcp_admission_windows')->sum('hits'));
    }

    public function test_independent_sql_workers_enforce_slot_and_release_only_owned_barriers(): void
    {
        $this->mcpConfiguration();
        [, $account] = $this->mcpAccount();
        $directory = storage_path('app/private/mcp-transport-'.Str::uuid());
        mkdir($directory, 0700, true);
        $workers = [];
        $start = function (string $action) use ($directory, $account, &$workers): Process {
            $process = new Process([PHP_BINARY, base_path('tests/support/mcp-concurrency-action.php')], base_path());
            $process->setInput(json_encode(['directory' => $directory, 'account' => $account->id, 'action' => $action], JSON_THROW_ON_ERROR));
            $process->setTimeout(30);
            $process->start();
            $workers[] = [$action, $process];

            return $process;
        };
        try {
            $holder = $start('hold');
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/locked.json')) {
                if (! $holder->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Independent SQL worker did not reach owned barrier');
                }
                usleep(10000);
            }
            $blocked = $start('attempt');
            $this->assertSame(0, $blocked->wait());
            $this->assertSame(['status' => 429, 'transaction_level' => 0], json_decode($blocked->getOutput(), true, 8, JSON_THROW_ON_ERROR));
            file_put_contents($directory.'/release.json.tmp', '{"release":true}');
            rename($directory.'/release.json.tmp', $directory.'/release.json');
            $this->assertSame(0, $holder->wait());
            $after = $start('after');
            $this->assertSame(0, $after->wait());
            $this->assertSame(['status' => 200, 'transaction_level' => 0], json_decode($after->getOutput(), true, 8, JSON_THROW_ON_ERROR));
        } finally {
            file_put_contents($directory.'/release.json', '{"release":true}');
            $results = [];
            foreach ($workers as [$action, $worker]) {
                if ($worker->isRunning()) {
                    $worker->wait();
                }
                $results[] = ['action' => $action, 'exit' => $worker->getExitCode(), 'stdout' => $worker->getOutput(), 'stderr' => $worker->getErrorOutput()];
            }
            $evidence = getenv('MCP_EVIDENCE_DIRECTORY');
            if ($evidence && is_dir($evidence)) {
                file_put_contents($evidence.'/concurrency-workers.json', json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
            $root = realpath(storage_path('app/private'));
            $owned = realpath($directory);
            $this->assertTrue($root && $owned && str_starts_with($owned, $root.DIRECTORY_SEPARATOR) && preg_match('/\Amcp-transport-[a-f0-9-]{36}\z/D', basename($owned)) === 1);
            app('files')->deleteDirectory($owned);
        }
    }

    private function probeRegistry(int $count = 30, int $descriptionBytes = 10, int $resultBytes = 10, bool $stream = false, ?string $name = null, bool $commitThenFail = false, bool $factoryOversized = false): void
    {
        // Test-only tools exercise the installed SDK. They are never registered in production.
        $this->app->bind(TransportServer::class, function ($app, array $parameters) use ($count, $descriptionBytes, $resultBytes, $stream, $name, $commitThenFail, $factoryOversized) {
            $tools = [];
            for ($index = 0; $index < $count; $index++) {
                $tools[] = new TransportProbeTool($name ?? sprintf('probe_%03d', $index), $descriptionBytes, $resultBytes, $stream, $commitThenFail, factoryOversized: $factoryOversized);
            }

            return new TransportProbeServer($parameters['transport'], $tools);
        });
    }

    public function test_real_sdk_registry_keyset_cursor_is_authenticated_scoped_and_byte_aware(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $membership] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->probeRegistry();
        $first = $this->rpc($bearer, $this->packet('tools/list'))->assertOk();
        $this->assertCount(25, $first->json('result.tools'));
        $cursor = $first->json('result.nextCursor');
        $next = $this->rpc($bearer, $this->packet('tools/list', ['cursor' => $cursor]))->assertOk();
        $this->assertCount(5, $next->json('result.tools'));
        $this->assertSame('probe_025', $next->json('result.tools.0.name'));
        $this->rpc($bearer, $this->packet('tools/list', ['cursor' => substr($cursor, 0, -1).'!']))->assertStatus(400);
        $membership->update(['role' => 'read-only']);
        $this->rpc($bearer, $this->packet('tools/list', ['cursor' => $cursor]))->assertStatus(400);
        $this->probeRegistry(3, 3100000);
        $large = $this->rpc($bearer, $this->packet('tools/list'))->assertOk();
        $this->assertCount(2, $large->json('result.tools'));
        $this->assertLessThanOrEqual(8388608, strlen($large->getContent()));
        $last = $this->rpc($bearer, $this->packet('tools/list', ['cursor' => $large->json('result.nextCursor')]))->assertOk();
        $this->assertSame('probe_002', $last->json('result.tools.0.name'));
        $this->probeRegistry(1, 8388608);
        $this->rpc($bearer, $this->packet('tools/list'))->assertStatus(413);
    }

    public function test_real_sdk_mirrored_tool_result_budget_counts_complete_wire_envelope(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->probeRegistry(1, 10, 100);
        $packet = $this->packet('tools/call', ['name' => 'probe_000', 'arguments' => (object) []]);
        $result = $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000'])->assertOk();
        $this->assertSame(json_decode($result->json('result.content.0.text'), true), $result->json('result.structuredContent'));
        $this->probeRegistry(1, 10, 4300000);
        $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000'])->assertStatus(413)->assertJsonPath('error.message', 'Result too large');
    }

    public function test_real_sdk_preserves_input_strings_and_refuses_deferred_tools_inside_slot(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->probeRegistry(1);
        $arguments = ['empty' => '', 'space' => '  preserve  ', 'ratio' => 1.25];
        $packet = $this->packet('tools/call', ['name' => 'probe_000', 'arguments' => (object) $arguments]);
        $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000', 'Authorization' => 'bearer '.$bearer])->assertOk()->assertJsonPath('result.structuredContent.arguments', $arguments);
        $packet['params']->arguments = null;
        $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000'])->assertStatus(400);
        $packet['params']->arguments = (object) [];
        $this->probeRegistry(1, stream: true);
        $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000'])->assertStatus(500)->assertDontSee('deferred-sensitive-value')->assertDontSee('Exception')->assertDontSee('vendor');
        $this->assertFalse(request()->attributes->get('mcp.probe_deferred', false));
        $this->assertTrue(app(Concurrency::class)->run($account->id, fn () => true));
    }

    public function test_session_is_bearer_bound_expiring_capped_and_safe_in_both_profiles(): void
    {
        $this->mcpConfiguration();
        config(['app.debug' => true]);
        [$user, $account] = $this->mcpAccount();
        [$token, , $bearer] = $this->mcpCredential($user, $account);
        [, , $otherBearer] = $this->mcpCredential($user, $account);
        $init = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']]];
        $session = $this->postJson('/mcp', $init, ['Authorization' => 'Bearer '.$bearer])->assertOk()->headers->get('MCP-Session-Id');
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $session];
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers)->assertNotFound();
        $headers['Authorization'] = 'Bearer '.$otherBearer;
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertNotFound();
        $headers['Authorization'] = 'Bearer '.$bearer;
        DB::table('mcp_sessions')->where('id', $session)->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertNotFound();
        $this->artisan('mcp:prune', ['--limit' => '1'])->assertSuccessful();
        $this->assertDatabaseMissing('mcp_sessions', ['id' => $session]);
        for ($index = 0; $index < 8; $index++) {
            $this->postJson('/mcp', $init, ['Authorization' => 'Bearer '.$bearer])->assertOk();
        }
        $this->postJson('/mcp', $init, ['Authorization' => 'Bearer '.$bearer])->assertStatus(429);
        foreach (['2025-11-25', '2026-07-28'] as $version) {
            $packet = $version === '2026-07-28' ? $this->packet('unknown-sensitive-value') : ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'unknown-sensitive-value'];
            $response = $version === '2026-07-28' ? $this->rpc($bearer, $packet) : $this->postJson('/mcp', $packet, $headers);
            $response->assertDontSee('unknown-sensitive-value')->assertDontSee('Exception')->assertDontSee('vendor')->assertHeader('Cache-Control', 'no-store');
        }
    }

    public function test_sdk_tool_exception_never_exposes_message_with_debug_enabled(): void
    {
        $this->mcpConfiguration();
        config(['app.debug' => true]);
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->probeRegistry(1);
        $packet = $this->packet('tools/call', ['name' => 'probe_000', 'arguments' => (object) ['fail' => true]]);
        $this->rpc($bearer, $packet, ['Mcp-Name' => 'probe_000'])->assertStatus(500)->assertDontSee('sdk-sensitive-value')->assertDontSee('Exception')->assertDontSee('vendor');
        $init = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']]], ['Authorization' => 'Bearer '.$bearer])->assertOk();
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $init->headers->get('MCP-Session-Id')];
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertStatus(202);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'probe_000', 'arguments' => ['fail' => true]]], $headers)->assertStatus(500)->assertDontSee('sdk-sensitive-value')->assertDontSee('Exception')->assertDontSee('vendor');
    }

    public function test_test_only_sdk_probe_preserves_committed_outcome_and_owned_reference_on_failure(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        // This probe commits an owned audit fixture, not a productive lists_create implementation.
        $this->probeRegistry(1, name: 'lists_create', commitThenFail: true);
        $response = $this->rpc($bearer, $this->packet('tools/call', ['name' => 'lists_create', 'arguments' => (object) ['list' => (object) ['name' => 'Synthetic probe', 'description' => '']]]), ['Mcp-Name' => 'lists_create'])->assertOk()->assertJsonPath('result.isError', true)->assertJsonPath('result.resultType', 'complete');
        $this->assertArrayNotHasKey('structuredContent', $response->json('result'));
        $dto = json_decode($response->json('result.content.0.text'), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('applied', $dto['error']['outcome']);
        $this->assertFalse($dto['error']['can_repeat_operation']);
        $this->assertSame('read_resource', $dto['error']['recovery']);
        $this->assertDatabaseHas('audit_events', ['id' => $dto['error']['reference']['id'], 'account_id' => $account->id, 'actor_id' => $user->id, 'action' => 'mcp.test_probe.applied']);
        $this->assertLessThanOrEqual(16384, strlen($response->json('result.content.0.text')));
        $response->assertDontSee('sdk-sensitive-value');
        $this->probeRegistry(1, resultBytes: 4300000, name: 'lists_get');
        $response = $this->rpc($bearer, $this->packet('tools/call', ['name' => 'lists_get', 'arguments' => (object) ['list_id' => 1]]), ['Mcp-Name' => 'lists_get'])->assertOk()->assertJsonPath('result.isError', true);
        $dto = json_decode($response->json('result.content.0.text'), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('result_too_large', $dto['error']['code']);
        $this->assertSame('not_applied', $dto['error']['outcome']);
        $this->assertArrayNotHasKey('structuredContent', $response->json('result'));
    }

    public function test_open_legacy_session_and_modern_registry_refresh_membership_capabilities(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $membership] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->app->bind(TransportServer::class, fn ($app, array $parameters) => new TransportProbeServer($parameters['transport'], [new TransportProbeTool('probe_read', 10, 10), new TransportProbeTool('probe_write', 10, 10, requiresWrite: true)]));
        $init = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic client', 'version' => '1']]], ['Authorization' => 'Bearer '.$bearer])->assertOk();
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $init->headers->get('MCP-Session-Id')];
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertStatus(202);
        $this->rpc($bearer, $this->packet('tools/list'))->assertJsonCount(2, 'result.tools');
        $membership->update(['role' => 'read-only']);
        $this->rpc($bearer, $this->packet('tools/list'))->assertJsonCount(1, 'result.tools')->assertJsonPath('result.tools.0.name', 'probe_read');
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers)->assertOk()->assertJsonCount(1, 'result.tools');
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'probe_write', 'arguments' => (object) []]], $headers)->assertStatus(400);
        $this->assertFalse(request()->attributes->get('mcp.probe_invoked', false));
        $this->rpc($bearer, $this->packet('tools/call', ['name' => 'probe_read', 'arguments' => (object) []]), ['Mcp-Name' => 'probe_read'])->assertOk();
    }

    public function test_factory_error_budget_projects_reference_without_truncating_or_losing_commit_outcome(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->probeRegistry(1, name: 'lists_create', commitThenFail: true, factoryOversized: true);
        $response = $this->rpc($bearer, $this->packet('tools/call', ['name' => 'lists_create', 'arguments' => (object) ['list' => (object) ['name' => 'Synthetic probe', 'description' => '']]]), ['Mcp-Name' => 'lists_create'])->assertOk();
        $text = $response->json('result.content.0.text');
        $this->assertLessThanOrEqual(16384, strlen($text));
        $dto = json_decode($text, true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('result_too_large', $dto['error']['code']);
        $this->assertSame('applied', $dto['error']['outcome']);
        $this->assertFalse($dto['error']['can_repeat_operation']);
        $this->assertSame(['kind', 'id'], array_keys($dto['error']['reference']));
        $this->assertDatabaseHas('audit_events', ['id' => $dto['error']['reference']['id'], 'account_id' => $account->id, 'action' => 'mcp.test_probe.applied']);
        $response->assertDontSee('factory-sensitive-value');
        $factory = ToolError::result('lists_create', 'internal_error', 'applied', ['kind' => 'activity', 'id' => str_repeat('a', 101)]);
        $invalid = json_decode($factory['content'][0]['text'], true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame('applied', $invalid['error']['outcome']);
        $this->assertArrayNotHasKey('reference', $invalid['error']);
        $this->assertSame('none', $invalid['error']['recovery']);
        $factory = ToolError::result('lists_create', 'internal_error', 'unknown', ['kind' => 'activity', 'id' => 'key.A_7:-value']);
        $this->assertSame('key.A_7:-value', json_decode($factory['content'][0]['text'], true, 16, JSON_THROW_ON_ERROR)['error']['reference']['id']);
        try {
            ToolError::result('probe_000', 'internal_error');
            $this->fail('Unknown operation was relabelled');
        } catch (ProtocolError $error) {
            $this->assertSame(-32603, $error->rpcCode);
        }
    }
}

class TransportProbeServer extends TransportServer
{
    public function __construct($transport, array $tools)
    {
        parent::__construct($transport);
        $this->tools = $tools;
    }
}

class TransportProbeTool extends Tool
{
    public function __construct(string $name, int $descriptionBytes, private int $resultBytes, private bool $stream = false, private bool $commitThenFail = false, private bool $requiresWrite = false, private bool $factoryOversized = false)
    {
        $this->name = $name;
        $this->description = str_repeat('d', $descriptionBytes);
    }

    public function eligibleForRegistration(): bool
    {
        return ! $this->requiresWrite || in_array('contacts.write', request()->attributes->get('mcp.context')->effectiveScopes(), true);
    }

    public function handle(Request $request): ResponseFactory|Response|\Generator
    {
        request()->attributes->get('mcp.context')->authorize([$this->requiresWrite ? 'contacts.write' : 'contacts.read']);
        request()->attributes->set('mcp.probe_invoked', true);
        if ($this->commitThenFail) {
            $context = request()->attributes->get('mcp.context');
            $event = DB::transaction(function () use ($context) {
                $context->authorize(['contacts.write'], true);

                return AuditEvent::create(['account_id' => $context->accountId(), 'actor_id' => $context->actorId(), 'action' => 'mcp.test_probe.applied', 'metadata' => ['synthetic' => true]]);
            });
            request()->attributes->set('mcp.outcome', 'applied');
            request()->attributes->set('mcp.reference', ['kind' => 'activity', 'id' => (string) $event->id]);
            if ($this->factoryOversized) {
                $reference = request()->attributes->get('mcp.reference');
                $reference['diagnostic'] = str_repeat('factory-sensitive-value', 20000);
                $result = ToolError::result($this->name, 'result_too_large', 'applied', $reference);

                return Response::error($result['content'][0]['text']);
            }
            throw new \RuntimeException('sdk-sensitive-value');
        }
        if ($request->get('fail')) {
            throw new \RuntimeException('sdk-sensitive-value');
        }
        if ($this->stream) {
            return (function () {
                request()->attributes->set('mcp.probe_deferred', true);
                yield Response::text('deferred-sensitive-value');
            })();
        }

        return Response::structured(['data' => ['value' => str_repeat('v', $this->resultBytes)], 'arguments' => $request->all()]);
    }
}
