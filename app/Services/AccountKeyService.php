<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Support\ApiError;
use App\Support\SendingApiInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountKeyService
{
    public static function utc(mixed $date): ?string
    {
        return $date === null ? null : CarbonImmutable::parse($date, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    public function candidate(Request $request): AccountApiKey
    {
        $headers = $request->headers->all('authorization');
        ApiError::require(count($headers) === 1 && strlen($headers[0]) <= 512 && (bool) preg_match('/^Bearer ([1-9][0-9]{0,19}\|[A-Za-z0-9]{64})$/iD', $headers[0], $match), 401);
        // Sanctum's constant-time SHA-256 comparison; no session guard is consulted.
        $key = AccountApiKey::findToken($match[1]);
        ApiError::require($key instanceof AccountApiKey, 401);

        return $key;
    }

    /** Caller owns the account mutex before taking any credential lock. */
    public function current(AccountApiKey $candidate, array $scopes = []): AccountApiKey
    {
        $key = AccountApiKey::where('account_id', $candidate->account_id)->whereKey($candidate->id)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', DB::raw('UTC_TIMESTAMP(6)')))->lockForUpdate()->first();
        ApiError::require($key !== null && $key->tokenable_type === Account::class && $key->tokenable_id == $key->account_id && hash_equals($candidate->token, $key->token), 401);
        ApiError::require(is_array($key->abilities) && $key->abilities !== [] && array_diff($key->abilities, SendingApiInput::SCOPES) === [], 401);
        ApiError::require(array_diff($scopes, $key->abilities) === [], 403);

        return $key;
    }

    public function admittedOperation(AccountApiKey $candidate, callable $callback): mixed
    {
        return $this->operation($candidate, [], function ($key) use ($callback) {
            $clock = DB::selectOne("SELECT DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%d %H:%i:00') AS window_start, 60 - SECOND(UTC_TIMESTAMP()) AS retry_after");
            $rows = [];
            foreach ([0 => 60, $key->id => 30] as $credential => $limit) {
                DB::table('api_admission_windows')->insertOrIgnore(['account_id' => $key->account_id, 'credential_id' => $credential, 'starts_at' => $clock->window_start, 'used' => 0]);
                $query = DB::table('api_admission_windows')->where('account_id', $key->account_id)->where('credential_id', $credential)->where('starts_at', $clock->window_start);
                if ((int) (clone $query)->lockForUpdate()->value('used') >= $limit) {
                    throw new ApiError(429, ['Retry-After' => (string) max(1, (int) $clock->retry_after)]);
                }
                $rows[] = $query;
            }
            foreach ($rows as $query) {
                $query->increment('used');
            }
            AccountApiKey::whereKey($key->id)->update(['last_used_at' => DB::raw('UTC_TIMESTAMP(6)')]);

            // Keep the account/credential mutex across admission and domain work.
            // Expected application failures roll back the domain savepoint only;
            // their authenticated HTTP admission still commits atomically.
            try {
                return DB::transaction(fn () => $callback($key));
            } catch (ApiError $error) {
                return $error->response();
            }
        });
    }

    /** Account -> credential -> source is the common operation lock order. */
    public function operation(AccountApiKey $candidate, array $scopes, callable $callback): mixed
    {
        $entryLevel = DB::transactionLevel();
        try {
            return DB::transaction(function () use ($candidate, $scopes, $callback) {
                ApiError::require(Account::whereKey($candidate->account_id)->lockForUpdate()->first() !== null, 401);

                return $callback($this->current($candidate, $scopes));
            }, 3);
        } catch (\Throwable $error) {
            // MySQL can abort the whole transaction on a deadlock. A safe HTTP
            // exception may already have replaced the driver exception, so a
            // nested rollback can itself fail on the now missing savepoint.
            // Restore the owned boundary explicitly; never leave phantom nesting.
            try {
                DB::rollBack($entryLevel);
            } catch (\Throwable) {
                DB::rollBack(0);
            }
            throw $error;
        }
    }

    public function metadata(AccountApiKey $key): array
    {
        return ['id' => $key->public_id, 'name' => $key->name, 'scopes' => $key->abilities, 'created_at' => self::utc($key->created_at), 'expires_at' => self::utc($key->expires_at), 'revoked_at' => self::utc($key->revoked_at), 'last_used_at' => self::utc($key->last_used_at)];
    }

    /** Shared domain grant rule; API/UI callers have already locked fresh authority. */
    public function create(int $account, array $data, array $ceiling, ?string $parentExpiry = null, ?int $parentKey = null, ?int $actor = null): array
    {
        // Both entrypoints pass normalized data. Keep the grant invariants in
        // the shared domain service too, rather than trusting a controller.
        $fields = $data;
        unset($fields['expires_at']);
        SendingApiInput::key((object) $fields);
        if (isset($data['expires_at'])) {
            ApiError::require(is_string($data['expires_at']));
            $data['expires_at'] = SendingApiInput::timestamp(str_replace(' ', 'T', $data['expires_at']).'Z');
        }
        ApiError::require(array_diff($data['scopes'], $ceiling) === [], 403);
        $expiry = array_key_exists('expires_at', $data) ? $data['expires_at'] : $parentExpiry;
        if ($parentExpiry !== null) {
            ApiError::require($expiry !== null && strcmp($expiry, $parentExpiry) <= 0, 403);
        }
        if ($expiry !== null) {
            ApiError::require((bool) DB::selectOne('SELECT CAST(? AS DATETIME(6)) > UTC_TIMESTAMP(6) AS valid', [$expiry])->valid);
        }
        $secret = Str::random(64);
        $key = new AccountApiKey;
        $key->forceFill(['public_id' => (string) Str::uuid(), 'account_id' => $account, 'tokenable_type' => Account::class, 'tokenable_id' => $account, 'name' => $data['name'], 'token' => hash('sha256', $secret), 'abilities' => $data['scopes'], 'expires_at' => $expiry, 'created_by' => $actor, 'granted_by_key_id' => $parentKey])->save();
        AuditEvent::create(['account_id' => $account, 'actor_id' => $actor, 'action' => 'api-key.created', 'metadata' => ['key_id' => $key->public_id, 'granting_key_id' => $parentKey ? AccountApiKey::findOrFail($parentKey)->public_id : null, 'scopes' => $data['scopes']]]);

        return ['key' => $this->metadata($key->fresh()), 'secret' => $key->id.'|'.$secret];
    }

    public function revoke(int $account, string $publicId, ?int $actor = null, ?int $credential = null): void
    {
        $key = AccountApiKey::where('account_id', $account)->where('public_id', $publicId)->lockForUpdate()->first();
        ApiError::require($key !== null, 404);
        if ($key->revoked_at === null) {
            AccountApiKey::whereKey($key->id)->update(['revoked_at' => DB::raw('UTC_TIMESTAMP(6)')]);
            AuditEvent::create(['account_id' => $account, 'actor_id' => $actor, 'action' => 'api-key.revoked', 'metadata' => ['key_id' => $publicId, 'credential_id' => $credential ? AccountApiKey::findOrFail($credential)->public_id : null]]);
        }
    }

    public function page(int $account, array $input): array
    {
        $after = 0;
        if (isset($input['cursor'])) {
            try {
                $cursor = json_decode(Crypt::decryptString($input['cursor']), true, 8, JSON_THROW_ON_ERROR);
                ApiError::require(is_array($cursor) && ($cursor['account'] ?? null) === $account && is_int($cursor['after'] ?? null) && $cursor['after'] > 0, 400);
                $after = $cursor['after'];
            } catch (\Throwable) {
                throw new ApiError(400);
            }
        }
        $limit = $input['limit'] ?? 20;
        $rows = AccountApiKey::where('account_id', $account)->where('id', '>', $after)->orderBy('id')->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return ['data' => $rows->map($this->metadata(...))->values()->all(), 'next_cursor' => $more ? Crypt::encryptString(json_encode(['account' => $account, 'after' => $rows->last()->id], JSON_THROW_ON_ERROR)) : null];
    }

    public function ui(string $scope, callable $callback): mixed
    {
        $member = app(AccountContext::class)->authorize($scope);

        return DB::transaction(function () use ($member, $scope, $callback) {
            Account::whereKey($member->account_id)->lockForUpdate()->firstOrFail();
            $current = Membership::where('account_id', $member->account_id)->where('user_id', auth()->id())->lockForUpdate()->first();
            abort_unless($current && Permissions::allows($current, $scope), 403);
            $ceiling = array_values(array_filter(SendingApiInput::SCOPES, fn ($ability) => Permissions::allows($current, $ability)));

            return $callback($current->account_id, $ceiling);
        }, 3);
    }
}
