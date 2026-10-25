<?php

namespace App\Services\Mcp;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\DB;

final class GrantService
{
    public function revoke(string $id, int $actorId, string $operation = 'connection.revoke'): void
    {
        abort_unless(in_array($operation, ['connection.revoke', 'oauth.revoke'], true), 500);
        $candidate = McpGrant::whereKey($id)->where('user_id', $actorId)->firstOrFail();
        DB::transaction(function () use ($candidate, $actorId, $operation): void {
            Account::whereKey($candidate->account_id)->lockForUpdate()->firstOrFail();
            Membership::where('account_id', $candidate->account_id)->where('user_id', $actorId)->lockForUpdate()->first();
            McpClient::whereKey($candidate->client_id)->lockForUpdate()->firstOrFail();
            $grant = McpGrant::whereKey($candidate->id)->where('user_id', $actorId)->lockForUpdate()->firstOrFail();
            $tokens = PersonalAccessToken::where('grant_id', $grant->id)->orderBy('id')->lockForUpdate()->get();
            if ($grant->revoked_at === null) {
                $grant->update(['revoked_at' => now()]);
                $tokens->each(fn ($token) => $token->update(['revoked_at' => now()]));
                DB::table('mcp_sessions')->whereIn('token_id', $tokens->modelKeys())->delete();
                app(Audit::class)->record($actorId, $grant->account_id, $grant->client_id, $grant->id, $operation, 'revoked');
                AuditEvent::create(['account_id' => $grant->account_id, 'actor_id' => $actorId, 'action' => 'mcp.connection.revoked', 'metadata' => ['grant_id' => $grant->id, 'client_id' => $grant->client_id]]);
            }
        }, 3);
    }
}
