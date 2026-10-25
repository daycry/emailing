<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Mcp\DelegatedContext;
use Illuminate\Support\Str;

trait McpFixtures
{
    protected function mcpConfiguration(): void
    {
        config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
    }

    protected function mcpAccount(?User $user = null, string $role = 'admin', array $scopes = []): array
    {
        $user ??= User::factory()->create(['email' => Str::uuid().'@example.test']);
        $account = Account::create(['name' => 'Synthetic workspace', 'owner_id' => $user->id]);
        $membership = Membership::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => $role, 'scopes' => $scopes]);

        return [$user, $account, $membership];
    }

    protected function mcpCredential(User $user, Account $account, ?array $scopes = null, ?array $abilities = null, ?McpClient $client = null): array
    {
        $scopes ??= DelegatedContext::scopes();
        $client ??= McpClient::create(['id' => (string) Str::uuid(), 'issuer' => config('mcp.issuer'), 'name' => 'Synthetic agent', 'redirect_uris' => ['http://127.0.0.1:18743/callback']]);
        $grant = McpGrant::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id, 'client_id' => $client->id, 'issuer' => config('mcp.issuer'), 'resource' => config('mcp.resource'), 'scopes' => $scopes, 'expires_at' => now()->addHour()]);
        $secret = Str::random(64);
        $token = new PersonalAccessToken;
        $token->forceFill(['tokenable_type' => User::class, 'tokenable_id' => $user->id, 'name' => 'MCP', 'token' => hash('sha256', $secret), 'abilities' => $abilities ?? $scopes, 'purpose' => 'mcp', 'account_id' => $account->id, 'client_id' => $client->id, 'grant_id' => $grant->id, 'issuer' => config('mcp.issuer'), 'resource' => config('mcp.resource'), 'expires_at' => $grant->expires_at])->save();

        return [$token, $grant, $token->id.'|'.$secret, $client];
    }
}
