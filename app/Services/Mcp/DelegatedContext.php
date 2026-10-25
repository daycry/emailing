<?php

namespace App\Services\Mcp;

use App\Models\Account;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Permissions;
use App\Support\ExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DelegatedContext implements ExecutionContext
{
    private function __construct(private readonly PersonalAccessToken $candidate, private readonly string $correlation) {}

    public static function verified(PersonalAccessToken $token): self
    {
        $context = new self(clone $token, (string) Str::uuid());
        $context->authorize();

        return $context;
    }

    public function actorId(): int
    {
        return (int) $this->candidate->tokenable_id;
    }

    public function accountId(): int
    {
        return (int) $this->candidate->account_id;
    }

    public static function scopes(): array
    {
        return ['activity.read', 'campaigns.read', 'campaigns.write', 'contacts.read', 'contacts.write', 'mail.send', 'suppressions.read', 'suppressions.write', 'templates.read', 'templates.write'];
    }

    public function authorize(array $scopes = [], bool $lock = false): Membership
    {
        abort_if($lock && DB::transactionLevel() === 0, 500);
        $candidate = $this->candidate;
        // Common mutex: account -> membership -> client -> grant -> token -> domain row.
        $account = Account::whereKey($this->accountId())->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $member = Membership::where('account_id', $this->accountId())->where('user_id', $this->actorId())->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $client = McpClient::whereKey($candidate->client_id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $grant = McpGrant::whereKey($candidate->grant_id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $token = PersonalAccessToken::whereKey($candidate->id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        abort_unless($account && $member && $client && $grant && $token, 401);
        abort_unless($client->issuer === config('mcp.issuer'), 401);
        abort_unless(in_array($member->role, ['admin', 'read-only', 'restricted'], true), 401);
        abort_unless($client->revoked_at === null && ($client->expires_at === null || $client->expires_at->isFuture()) && $grant->revoked_at === null && $token->revoked_at === null && $grant->expires_at->isFuture() && $token->expires_at->isFuture(), 401);
        abort_unless($token->purpose === 'mcp' && $token->tokenable_type === User::class && (int) $token->tokenable_id === $this->actorId() && $grant->user_id === $this->actorId() && $grant->account_id === $this->accountId() && $token->account_id === $grant->account_id && $token->grant_id === $grant->id && $token->client_id === $grant->client_id && $client->id === $grant->client_id && hash_equals($candidate->token, $token->token), 401);
        abort_unless(is_string(config('mcp.issuer')) && is_string(config('mcp.resource')) && $grant->issuer === config('mcp.issuer') && $token->issuer === $grant->issuer && $grant->resource === config('mcp.resource') && $token->resource === $grant->resource, 401);
        abort_unless(is_array($grant->scopes) && is_array($token->abilities) && array_diff($grant->scopes, self::scopes()) === [] && array_diff($token->abilities, $grant->scopes) === [], 401);
        foreach ($scopes as $scope) {
            abort_unless(in_array($scope, $grant->scopes, true) && in_array($scope, $token->abilities, true) && Permissions::allows($member, $scope), 403);
        }

        return $member;
    }

    public function effectiveScopes(): array
    {
        $member = $this->authorize();
        $grant = McpGrant::findOrFail($this->candidate->grant_id);
        $token = PersonalAccessToken::findOrFail($this->candidate->id);

        return array_values(array_filter(array_intersect($grant->scopes, $token->abilities), fn ($scope) => Permissions::allows($member, $scope)));
    }

    public function provenance(): array
    {
        return ['kind' => 'mcp_grant', 'grant_id' => $this->candidate->grant_id, 'client_id' => $this->candidate->client_id, 'trace_id' => $this->correlation];
    }

    public function correlationId(): string
    {
        return $this->correlation;
    }
}
