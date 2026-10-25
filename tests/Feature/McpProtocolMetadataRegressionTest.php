<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpProtocolMetadataRegressionTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private const VERSION = 'io.modelcontextprotocol/protocolVersion';

    private const CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';

    private const MARKER = 'synthetic-never-reflect-metadata';

    private function writeParameters(string $name): array
    {
        return ['name' => 'lists_create', 'arguments' => (object) ['list' => (object) ['name' => $name, 'description' => '']]];
    }

    private function packet(array $parameters): array
    {
        // Preserve the caller's exact shape: never inject metadata into negatives.
        return ['jsonrpc' => '2.0', 'id' => 101, 'method' => 'tools/call', 'params' => (object) $parameters];
    }

    public function test_modern_metadata_and_mirror_negatives_reject_before_a_valid_mutation_can_execute(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $headers = ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'lists_create'];
        $modern = (object) [self::VERSION => '2026-07-28', self::CAPABILITIES => (object) []];
        $control = $this->writeParameters('Synthetic modern control before negatives');
        $control['_meta'] = $modern;
        $this->postJson('/mcp', $this->packet($control), $headers)->assertOk()->assertJsonMissingPath('error');
        $this->assertDatabaseHas('contact_lists', ['account_id' => $account->id, 'name' => 'Synthetic modern control before negatives']);
        $this->assertDatabaseCount('contact_lists', 1);

        $cases = [
            'absent' => [false, null, -32022],
            'empty object' => [true, (object) [], -32022],
            'null' => [true, null, -32022],
            'array' => [true, [], -32600],
            'string' => [true, self::MARKER, -32600],
            'number' => [true, 17, -32600],
            'boolean' => [true, false, -32600],
            'version only' => [true, (object) [self::VERSION => '2026-07-28'], -32600],
            'capabilities only' => [true, (object) [self::CAPABILITIES => (object) []], -32022],
            'legacy version' => [true, (object) [self::VERSION => '2025-11-25', self::CAPABILITIES => (object) []], -32022],
            'unknown version' => [true, (object) [self::VERSION => self::MARKER, self::CAPABILITIES => (object) []], -32022],
            'numeric version' => [true, (object) [self::VERSION => 2026, self::CAPABILITIES => (object) []], -32022],
            'object version' => [true, (object) [self::VERSION => (object) [], self::CAPABILITIES => (object) []], -32022],
        ];
        foreach ([null, [], self::MARKER, 17, false] as $index => $capabilities) {
            $cases['invalid capabilities '.$index] = [true, (object) [self::VERSION => '2026-07-28', self::CAPABILITIES => $capabilities], -32600];
        }
        foreach ($cases as $label => [$present, $metadata, $code]) {
            $parameters = $this->writeParameters(self::MARKER);
            if ($present) {
                $parameters['_meta'] = $metadata;
            }
            $response = $this->postJson('/mcp', $this->packet($parameters), $headers);
            $this->assertSame(400, $response->status(), $label);
            $response->assertJsonPath('error.code', $code)->assertJsonMissingPath('result')->assertHeader('Cache-Control', 'no-store')->assertDontSee(self::MARKER);
            $this->assertDatabaseCount('contact_lists', 1);
        }
        foreach (['MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name'] as $header) {
            $missing = $headers;
            unset($missing[$header]);
            $divergent = array_replace($headers, [$header => self::MARKER]);
            foreach ([$missing, $divergent] as $negativeHeaders) {
                $parameters = $this->writeParameters(self::MARKER);
                $parameters['_meta'] = $modern;
                $this->postJson('/mcp', $this->packet($parameters), $negativeHeaders)->assertStatus(400)->assertJsonPath('error.code', -32020)->assertJsonMissingPath('result')->assertHeader('Cache-Control', 'no-store')->assertDontSee(self::MARKER);
                $this->assertDatabaseCount('contact_lists', 1);
            }
        }
        $control = $this->writeParameters('Synthetic modern control after negatives');
        $control['_meta'] = $modern;
        $this->postJson('/mcp', $this->packet($control), $headers)->assertOk()->assertJsonMissingPath('error');
        $this->assertDatabaseCount('contact_lists', 2);
        $this->assertDatabaseCount('mcp_sessions', 0);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_partial_modern_metadata_never_downgrades_into_an_initialized_legacy_session(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $initial = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'Synthetic legacy metadata control', 'version' => '1']]], ['Authorization' => 'Bearer '.$bearer])->assertOk()->assertJsonPath('result.protocolVersion', '2025-11-25');
        $headers = ['Authorization' => 'Bearer '.$bearer, 'MCP-Protocol-Version' => '2025-11-25', 'MCP-Session-Id' => $initial->headers->get('MCP-Session-Id')];
        $this->assertNotEmpty($headers['MCP-Session-Id']);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $headers)->assertStatus(202);
        $this->postJson('/mcp', $this->packet($this->writeParameters('Synthetic legacy control before negatives')), $headers)->assertOk()->assertJsonMissingPath('error');
        $this->assertDatabaseHas('contact_lists', ['account_id' => $account->id, 'name' => 'Synthetic legacy control before negatives']);
        $this->assertDatabaseCount('contact_lists', 1);
        foreach ([
            [(object) [self::VERSION => '2026-07-28'], -32600],
            [(object) [self::CAPABILITIES => (object) []], -32022],
            [(object) [self::VERSION => '2025-11-25'], -32022],
            [(object) [self::VERSION => '2025-11-25', self::CAPABILITIES => (object) []], -32022],
            [(object) [self::VERSION => '2026-07-28', self::CAPABILITIES => (object) []], -32600],
        ] as [$metadata, $code]) {
            $parameters = $this->writeParameters(self::MARKER);
            $parameters['_meta'] = $metadata;
            $this->postJson('/mcp', $this->packet($parameters), $headers)->assertStatus(400)->assertJsonPath('error.code', $code)->assertJsonMissingPath('result')->assertDontSee(self::MARKER);
            $this->assertDatabaseCount('contact_lists', 1);
        }
        $this->postJson('/mcp', $this->packet($this->writeParameters('Synthetic legacy control after negatives')), $headers)->assertOk()->assertJsonMissingPath('error');
        $this->assertDatabaseCount('contact_lists', 2);
        $this->assertDatabaseCount('mcp_sessions', 1);
        $this->assertSame(0, DB::transactionLevel());
    }
}
