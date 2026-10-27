<?php

namespace Tests\Feature;

use App\Models\MailSender;
use App\Models\Membership;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\GrantService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

/** Connection metadata, effective authority and account-scoped sender projection. */
class McpConnectionInfoTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    public function test_sender_projection_is_account_scoped_and_grant_bound_without_dropped_rows(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account, ['mail.send']);
        $owned = [];
        foreach (['one', 'two', 'three'] as $suffix) {
            $owned[] = MailSender::create(['account_id' => $account->id, 'email' => $suffix.'@example.test',
                'name' => 'Synthetic '.$suffix, 'lab_authorized' => true])->id;
        }
        MailSender::create(['account_id' => $account->id, 'email' => 'hidden@example.test',
            'name' => 'Synthetic unauthorized sender', 'lab_authorized' => false]);
        [$otherUser, $otherAccount] = $this->mcpAccount();
        $foreign = MailSender::create(['account_id' => $otherAccount->id, 'email' => 'foreign@example.test',
            'name' => 'Synthetic foreign sender', 'lab_authorized' => true]);
        [, , $otherGrant] = $this->mcpCredential($user, $account, ['mail.send']);
        [, , $otherTenant] = $this->mcpCredential($otherUser, $otherAccount, ['mail.send']);

        $page = $this->success($bearer, ['senders' => ['limit' => 1]])['data']['senders'];
        $this->assertTrue($page['page']['has_more']);
        $cursor = $page['page']['next_cursor'];
        $this->assertIsString($cursor);
        $this->failure($otherGrant, ['senders' => ['limit' => 1, 'cursor' => $cursor]], 'cursor_invalid');
        $this->failure($otherTenant, ['senders' => ['limit' => 1, 'cursor' => $cursor]], 'cursor_invalid');
        $seen = array_column($page['items'], 'id');
        for ($remaining = 2; $remaining > 0; $remaining--) {
            $page = $this->success($bearer, ['senders' => ['limit' => 1, 'cursor' => $cursor]])['data']['senders'];
            $this->assertCount(1, $page['items']);
            $this->assertTrue($page['items'][0]['lab_authorized']);
            $seen[] = $page['items'][0]['id'];
            $this->assertSame($remaining > 1, $page['page']['has_more']);
            $cursor = $page['page']['next_cursor'];
        }
        rsort($owned);
        $this->assertSame($owned, $seen);
        $this->assertNull($cursor);
        $foreignPage = $this->success($otherTenant, ['senders' => ['limit' => 1, 'cursor' => null]])['data']['senders'];
        $this->assertSame([$foreign->id], array_column($foreignPage['items'], 'id'));
        $this->assertFalse($foreignPage['page']['has_more']);
        $this->assertNull($foreignPage['page']['next_cursor']);
        $this->assertDatabaseCount('api_mail_requests', 0);
    }

    public function test_metadata_respects_token_and_live_membership_ceilings_and_cursor_revision(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$token, $grant, $bearer] = $this->mcpCredential($user, $account, null, ['mail.send', 'activity.read']);
        foreach (['one', 'two'] as $suffix) {
            MailSender::create(['account_id' => $account->id, 'email' => $suffix.'@example.test',
                'name' => 'Synthetic '.$suffix, 'lab_authorized' => true]);
        }
        $before = $this->success($bearer, ['senders' => ['limit' => 1]]);
        $this->assertSame(DelegatedContext::scopes(), $before['data']['connection']['granted_scopes']);
        $this->assertEqualsCanonicalizing(['mail.send', 'activity.read'], $before['data']['connection']['effective_scopes']);
        $cursor = $before['data']['senders']['page']['next_cursor'];
        $this->assertIsString($cursor);

        Membership::where('account_id', $account->id)->where('user_id', $user->id)
            ->update(['role' => 'restricted', 'scopes' => json_encode(['mail.send', 'contacts.read'], JSON_THROW_ON_ERROR)]);
        $current = $this->success($bearer);
        $this->assertSame(['mail.send'], $current['data']['connection']['effective_scopes']);
        $this->assertSame($grant->id, $current['data']['connection']['public_id']);
        $this->assertArrayNotHasKey('senders', $current['data']);
        // Sending remains authorized, but a cursor from the prior membership revision does not.
        $this->failure($bearer, ['senders' => ['limit' => 1, 'cursor' => $cursor]], 'cursor_invalid');
        $this->assertCount(1, $this->success($bearer, ['senders' => ['limit' => 1]])['data']['senders']['items']);

        Membership::where('account_id', $account->id)->where('user_id', $user->id)
            ->update(['role' => 'read-only', 'scopes' => json_encode([], JSON_THROW_ON_ERROR)]);
        $this->assertSame(['activity.read'], $this->success($bearer)['data']['connection']['effective_scopes']);
        $this->failure($bearer, ['senders' => (object) []], 'forbidden');
        $this->assertSame(['mail.send', 'activity.read'], $token->fresh()->abilities);
        $this->assertSame(DelegatedContext::scopes(), $grant->fresh()->scopes);
        $this->assertDatabaseCount('api_mail_requests', 0);
    }

    public function test_connection_metadata_expires_at_earliest_credential_boundary_and_revokes_live(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$token, $grant, $bearer] = $this->mcpCredential($user, $account, []);
        $token->forceFill(['expires_at' => now()->addMinutes(5)])->save();
        $token->refresh();
        $data = $this->success($bearer)['data'];
        $this->assertSame($token->expires_at->getTimestamp(), CarbonImmutable::parse($data['connection']['access_expires_at'])->getTimestamp());
        $this->assertSame(config('mcp.issuer'), $data['server']['issuer_uri']);
        $this->assertSame(config('mcp.resource'), $data['server']['resource_uri']);
        $this->assertSame(['user_id' => $user->id], $data['principal']);
        $this->assertSame(['id' => $account->id, 'name' => $account->name], $data['account']);

        // Even a stored token with a later expiry cannot advertise authority beyond its grant.
        $token->forceFill(['expires_at' => now()->addHours(2)])->save();
        $current = $this->success($bearer)['data']['connection'];
        $this->assertSame($grant->expires_at->getTimestamp(), CarbonImmutable::parse($current['access_expires_at'])->getTimestamp());
        $token->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->rpc($bearer)->assertUnauthorized();

        [, $revocable, $secondBearer] = $this->mcpCredential($user, $account, []);
        $this->success($secondBearer);
        app(GrantService::class)->revoke($revocable->id, $user->id);
        $this->rpc($secondBearer)->assertUnauthorized();
        $this->assertDatabaseCount('api_mail_requests', 0);
    }

    private function success(string $bearer, array $arguments = []): array
    {
        $response = $this->rpc($bearer, $arguments)->assertOk()->assertJsonPath('result.isError', false);
        $dto = $response->json('result.structuredContent');
        $this->assertSame($dto, json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertTrue(ContractArguments::valid(json_decode(json_encode($dto, JSON_THROW_ON_ERROR)), ContractArguments::operation('connection_info')['outputSchema']));

        return $dto;
    }

    private function failure(string $bearer, array $arguments, string $code): void
    {
        $response = $this->rpc($bearer, $arguments)->assertOk()->assertJsonPath('result.isError', true);
        $dto = json_decode($response->json('result.content.0.text'), true, 64, JSON_THROW_ON_ERROR);
        $this->assertSame($code, $dto['error']['code']);
        $this->assertSame('not_applied', $dto['error']['outcome']);
        $this->assertFalse($dto['error']['can_repeat_operation']);
    }

    private function rpc(string $bearer, array $arguments = [])
    {
        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'connection_info', 'arguments' => (object) $arguments,
                '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]],
            ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'connection_info']);
    }
}
