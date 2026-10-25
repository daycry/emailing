<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\McpAuthorizationCode;
use App\Models\McpAuthorizationRequest;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class McpPrune extends Command
{
    protected $signature = 'mcp:prune {--limit=100 : Maximum rows per finite stage, at most 1000}';

    protected $description = 'Expire delegated authority and prune unreferenced OAuth data in bounded batches.';

    private function deleteUnreferenced(callable $delete): void
    {
        try {
            $delete();
        } catch (QueryException $error) {
            // Foreign keys preserve provenance, including accepted work added by later migrations.
            if (($error->errorInfo[1] ?? null) !== 1451) {
                throw $error;
            }
        }
    }

    public function handle(): int
    {
        $raw = $this->option('limit');
        if (! is_string($raw) || ! preg_match('/\A[1-9][0-9]{0,3}\z/D', $raw) || (int) $raw > 1000) {
            return self::INVALID;
        }
        $limit = (int) $raw;
        $now = now();
        $audits = DB::table('mcp_audit_events')->where('created_at', '<', $now->copy()->subDays(365))->orderBy('id')->limit($limit)->pluck('id');
        DB::table('mcp_audit_events')->whereIn('id', $audits)->delete();
        $sessions = DB::table('mcp_sessions')->where('expires_at', '<=', $now)->orderBy('id')->limit($limit)->pluck('id');
        DB::table('mcp_sessions')->whereIn('id', $sessions)->delete();
        $expired = McpGrant::whereNull('revoked_at')->where('expires_at', '<=', $now)->orderBy('id')->limit($limit)->get();
        foreach ($expired as $candidate) {
            DB::transaction(function () use ($candidate, $now): void {
                Account::whereKey($candidate->account_id)->lockForUpdate()->firstOrFail();
                Membership::where('account_id', $candidate->account_id)->where('user_id', $candidate->user_id)->lockForUpdate()->first();
                McpClient::whereKey($candidate->client_id)->lockForUpdate()->firstOrFail();
                $grant = McpGrant::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                $tokens = PersonalAccessToken::where('grant_id', $grant->id)->orderBy('id')->lockForUpdate()->get();
                if ($grant->revoked_at === null && $grant->expires_at->lte($now)) {
                    $grant->update(['revoked_at' => $now]);
                    $tokens->each(fn ($token) => $token->update(['revoked_at' => $now]));
                }
            }, 3);
        }
        foreach (PersonalAccessToken::where(fn ($q) => $q->where('expires_at', '<=', $now->copy()->subDays(30))->orWhere('revoked_at', '<=', $now->copy()->subDays(30)))->orderBy('id')->limit($limit)->get() as $token) {
            $this->deleteUnreferenced(fn () => PersonalAccessToken::whereKey($token->id)->delete());
        }
        foreach (McpAuthorizationCode::where('expires_at', '<=', $now->copy()->subDay())->orderBy('id')->limit($limit)->get() as $code) {
            $this->deleteUnreferenced(fn () => McpAuthorizationCode::whereKey($code->id)->delete());
        }
        $pending = McpAuthorizationRequest::where('expires_at', '<=', $now->copy()->subDay())->orderBy('id')->limit($limit)->pluck('id');
        McpAuthorizationRequest::whereIn('id', $pending)->delete();
        $windows = DB::table('mcp_admission_windows')->where('expires_at', '<=', $now->copy()->subHours(48))->orderBy('key')->limit($limit)->pluck('key');
        DB::table('mcp_admission_windows')->whereIn('key', $windows)->delete();
        foreach (McpGrant::where('revoked_at', '<=', $now->copy()->subDays(90))->orderBy('id')->limit($limit)->get() as $grant) {
            $this->deleteUnreferenced(fn () => McpGrant::whereKey($grant->id)->delete());
        }
        foreach (McpClient::where('dynamic', true)->where('expires_at', '<=', $now)->orderBy('id')->limit($limit)->get() as $client) {
            $this->deleteUnreferenced(fn () => McpClient::whereKey($client->id)->delete());
        }
        $this->info('Finite MCP retention pass completed; referenced provenance was preserved.');

        return self::SUCCESS;
    }
}
