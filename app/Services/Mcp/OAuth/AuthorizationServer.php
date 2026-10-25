<?php

namespace App\Services\Mcp\OAuth;

use App\Models\Account;
use App\Models\McpAuthorizationCode;
use App\Models\McpAuthorizationRequest;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Mcp\Audit;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\GrantService;
use App\Services\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthorizationServer
{
    public function __construct(private Metadata $metadata, private RedirectPolicy $redirects) {}

    public function client(string $id, bool $lock = false): McpClient
    {
        $client = McpClient::whereKey($id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        OAuthError::require($client !== null && $client->id === $id && $client->issuer === $this->metadata->issuer() && $client->revoked_at === null && ($client->expires_at === null || $client->expires_at->isFuture()), 'invalid_client');

        return $client;
    }

    public function register(array $input, bool $dynamic = true, bool $nativePort = false): McpClient
    {
        $this->metadata->issuer();
        OAuthError::require(! $dynamic || config('mcp.dynamic_registration'), 'not_found', 404);
        $name = Input::clientName($input);
        OAuthError::require(! preg_match('/[\x00-\x1F\x7F]/', $name), 'invalid_client_metadata');
        OAuthError::require(($input['token_endpoint_auth_method'] ?? null) === 'none', 'invalid_client_metadata');
        OAuthError::require(! array_key_exists('grant_types', $input) || $input['grant_types'] === ['authorization_code'], 'invalid_client_metadata');
        OAuthError::require(! array_key_exists('response_types', $input) || $input['response_types'] === ['code'], 'invalid_client_metadata');
        OAuthError::require(! array_key_exists('application_type', $input) || in_array($input['application_type'], ['native', 'web'], true), 'invalid_client_metadata');
        $redirects = $input['redirect_uris'] ?? null;
        OAuthError::require(is_array($redirects) && array_is_list($redirects) && count($redirects) >= 1 && count($redirects) <= 10 && count(array_unique($redirects, SORT_REGULAR)) === count($redirects), 'invalid_client_metadata');
        foreach ($redirects as $redirect) {
            OAuthError::require(is_string($redirect), 'invalid_redirect_uri');
            $this->redirects->validate($redirect);
            if ($dynamic) {
                $parts = parse_url($redirect);
                $allowed = ($parts['scheme'] ?? null) === 'https' ? config('mcp.registration_https_hosts', []) : config('mcp.registration_loopback_profiles', []);
                OAuthError::require(in_array($parts['host'], $allowed, true), 'invalid_redirect_uri');
            }
        }

        return DB::transaction(function () use ($name, $redirects, $dynamic, $nativePort): McpClient {
            $mutex = hash('sha256', $this->metadata->issuer());
            DB::table('mcp_issuer_mutexes')->insertOrIgnore(['key' => $mutex]);
            DB::table('mcp_issuer_mutexes')->where('key', $mutex)->lockForUpdate()->first();
            if ($dynamic) {
                $active = McpClient::where('issuer', $this->metadata->issuer())->where('dynamic', true)->whereNull('revoked_at')->where('expires_at', '>', now())->count();
                OAuthError::require($active < 100, 'temporarily_unavailable', 429);
            }

            return McpClient::create(['id' => (string) Str::uuid(), 'issuer' => $this->metadata->issuer(), 'name' => $name, 'redirect_uris' => $redirects, 'native_port_relaxation' => ! $dynamic && $nativePort, 'dynamic' => $dynamic, 'expires_at' => $dynamic ? now()->addDays(30) : null]);
        }, 3);
    }

    public function start(Request $request, array $input): McpAuthorizationRequest
    {
        $this->metadata->issuer();
        $client = $this->client(Input::clientId($input));
        $redirect = Input::string($input, 'redirect_uri');
        OAuthError::require($this->redirects->accepts($client, $redirect), 'invalid_redirect_uri');
        OAuthError::require(($input['response_type'] ?? null) === 'code', 'unsupported_response_type');
        OAuthError::require(Input::string($input, 'resource') === $this->metadata->resource(), 'invalid_target');
        OAuthError::require(($input['code_challenge_method'] ?? null) === 'S256');
        $challenge = Input::string($input, 'code_challenge', 43, 43, '/\A[A-Za-z0-9_-]{43}\z/D');
        $state = array_key_exists('state', $input) ? Input::string($input, 'state', 1, 2048, '/\A[\x20-\x7E]+\z/D') : null;
        OAuthError::require(! isset($input['scope']) || is_string($input['scope']), 'invalid_scope');
        $scopes = Input::scopes($input['scope'] ?? '');
        $binding = $request->session()->get('mcp.browser');
        if (! is_string($binding)) {
            $binding = Str::random(64);
            $request->session()->put('mcp.browser', $binding);
        }

        return DB::transaction(function () use ($request, $binding, $client, $redirect, $scopes, $state, $challenge): McpAuthorizationRequest {
            if ($request->user()) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $count = McpAuthorizationRequest::where('user_id', $request->user()->id)->where('client_id', $client->id)->whereNull('consumed_at')->where('expires_at', '>', now())->count();
                OAuthError::require($count < 5, 'temporarily_unavailable', 429);
            }
            $pending = McpAuthorizationRequest::create(['id' => (string) Str::uuid(), 'user_id' => $request->user()?->id, 'browser_hash' => hash('sha256', $binding), 'client_id' => $client->id, 'redirect_uri' => $redirect, 'resource' => $this->metadata->resource(), 'scopes' => $scopes, 'state' => $state, 'code_challenge' => $challenge, 'expires_at' => now()->addMinutes(10)]);

            return $this->pending($request, $pending->id);
        }, 3);
    }

    public function pending(Request $request, string $id): McpAuthorizationRequest
    {
        $this->metadata->issuer();
        OAuthError::require(Str::isUuid($id));
        $binding = $request->session()->get('mcp.browser');
        OAuthError::require(is_string($binding));
        $candidate = McpAuthorizationRequest::find($id);
        OAuthError::require($candidate !== null && hash_equals($candidate->browser_hash, hash('sha256', $binding)) && $candidate->consumed_at === null && $candidate->expires_at->isFuture());
        $client = $this->client($candidate->client_id);
        OAuthError::require($candidate->resource === $this->metadata->resource() && $this->redirects->accepts($client, $candidate->redirect_uri));
        if ($request->user()) {
            // A pending request is browser-owned and becomes bound to exactly one local actor.
            return DB::transaction(function () use ($candidate, $request): McpAuthorizationRequest {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $pending = McpAuthorizationRequest::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                OAuthError::require($pending->user_id === null || (int) $pending->user_id === $request->user()->id, 'access_denied', 403);
                if ($pending->user_id === null) {
                    $count = McpAuthorizationRequest::where('user_id', $request->user()->id)->where('client_id', $pending->client_id)->whereNull('consumed_at')->where('expires_at', '>', now())->count();
                    OAuthError::require($count < 5, 'temporarily_unavailable', 429);
                    $pending->update(['user_id' => $request->user()->id]);
                }

                return $pending;
            }, 3);
        }
        OAuthError::require($candidate->user_id === null, 'access_denied', 403);

        return $candidate;
    }

    public function accounts(int $user): array
    {
        return Membership::with('account')->where('user_id', $user)->orderBy('account_id')->get()->filter(fn ($member) => in_array($member->role, ['admin', 'read-only', 'restricted'], true))->map(fn ($member) => ['id' => $member->account_id, 'name' => $member->account->name, 'scopes' => array_values(array_filter(DelegatedContext::scopes(), fn ($scope) => Permissions::allows($member, $scope)))])->all();
    }

    public function decision(Request $request, array $input): string
    {
        $pending = $this->pending($request, Input::string($input, 'request_id', 36, 36));
        OAuthError::require($request->user() !== null && (int) $pending->user_id === $request->user()->id, 'access_denied', 403);
        try {
            return $this->decide($request, $pending, $input);
        } catch (OAuthError $error) {
            // Account selection has not acquired valid authority; never audit a forged account.
            app(Audit::class)->record($request->user()->id, null, $pending->client_id, null, 'oauth.authorize', 'denied');
            throw $error;
        }
    }

    private function decide(Request $request, McpAuthorizationRequest $pending, array $input): string
    {
        OAuthError::require(in_array($input['decision'] ?? null, ['allow', 'deny'], true));
        $allow = $input['decision'] === 'allow';
        $accountId = $input['account_id'] ?? null;
        $scopes = $input['scopes'] ?? null;
        if ($allow) {
            OAuthError::require((is_int($accountId) && $accountId > 0 && $accountId <= 9007199254740991) || (is_string($accountId) && preg_match('/\A[1-9][0-9]{0,15}\z/D', $accountId) && (int) $accountId <= 9007199254740991), 'invalid_request', 422);
            OAuthError::require(is_array($scopes) && array_is_list($scopes) && count($scopes) <= 10 && count(array_filter($scopes, 'is_string')) === count($scopes) && count(array_unique($scopes)) === count($scopes) && array_diff($scopes, $pending->scopes) === [], 'invalid_scope', 422);
        }

        return DB::transaction(function () use ($request, $pending, $allow, $accountId, $scopes): string {
            if ($allow) {
                $account = Account::whereKey((int) $accountId)->lockForUpdate()->first();
                $member = Membership::where('account_id', (int) $accountId)->where('user_id', $request->user()->id)->lockForUpdate()->first();
                OAuthError::require($account !== null && $member !== null && in_array($member->role, ['admin', 'read-only', 'restricted'], true), 'access_denied', 403);
                foreach ($scopes as $scope) {
                    OAuthError::require(Permissions::allows($member, $scope), 'access_denied', 403);
                }
            }
            $client = $this->client($pending->client_id, true);
            $current = McpAuthorizationRequest::whereKey($pending->id)->lockForUpdate()->firstOrFail();
            OAuthError::require($current->consumed_at === null && $current->expires_at->isFuture() && (int) $current->user_id === $request->user()->id && hash_equals($current->browser_hash, $pending->browser_hash), 'invalid_request', 409);
            OAuthError::require($this->redirects->accepts($client, $current->redirect_uri) && $current->resource === $this->metadata->resource());
            $current->update(['consumed_at' => now()]);
            if (! $allow) {
                app(Audit::class)->record($request->user()->id, null, $client->id, null, 'oauth.authorize', 'denied');

                return $this->redirect($current, ['error' => 'access_denied']);
            }
            $grant = McpGrant::create(['id' => (string) Str::uuid(), 'account_id' => $account->id, 'user_id' => $request->user()->id, 'client_id' => $client->id, 'issuer' => $this->metadata->issuer(), 'resource' => $this->metadata->resource(), 'scopes' => $scopes, 'expires_at' => now()->addHour()]);
            $code = Str::random(64);
            McpAuthorizationCode::create(['code_hash' => hash('sha256', $code), 'grant_id' => $grant->id, 'client_id' => $client->id, 'redirect_uri' => $current->redirect_uri, 'resource' => $current->resource, 'code_challenge' => $current->code_challenge, 'expires_at' => now()->addSeconds(300)]);
            app(Audit::class)->record($request->user()->id, $account->id, $client->id, $grant->id, 'oauth.authorize', 'approved');

            return $this->redirect($current, ['code' => $code]);
        }, 3);
    }

    private function redirect(McpAuthorizationRequest $pending, array $parameters): string
    {
        $parameters['iss'] = $this->metadata->issuer();
        if ($pending->state !== null) {
            $parameters['state'] = $pending->state;
        }

        return $pending->redirect_uri.(str_contains($pending->redirect_uri, '?') ? '&' : '?').http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    public function token(array $input): array
    {
        $this->metadata->issuer();
        OAuthError::require(($input['grant_type'] ?? null) === 'authorization_code', 'unsupported_grant_type');
        $clientId = Input::clientId($input);
        $this->client($clientId);
        $code = Input::string($input, 'code', 32, 512);
        $redirect = Input::string($input, 'redirect_uri');
        OAuthError::require(Input::string($input, 'resource') === $this->metadata->resource(), 'invalid_target');
        $verifier = Input::string($input, 'code_verifier', 43, 128, '/\A[A-Za-z0-9._~-]+\z/D');
        $candidate = McpAuthorizationCode::where('code_hash', hash('sha256', $code))->first();
        OAuthError::require($candidate !== null, 'invalid_grant');
        $reference = McpGrant::find($candidate->grant_id);
        OAuthError::require($reference !== null, 'invalid_grant');
        // Return failures from inside the transaction so verifier attempts/replay revocation commit.
        $result = DB::transaction(function () use ($reference, $candidate, $clientId, $redirect, $verifier): array|OAuthError {
            $account = Account::whereKey($reference->account_id)->lockForUpdate()->first();
            $member = Membership::where('account_id', $reference->account_id)->where('user_id', $reference->user_id)->lockForUpdate()->first();
            $client = $this->client($clientId, true);
            $grant = McpGrant::whereKey($reference->id)->lockForUpdate()->first();
            $code = McpAuthorizationCode::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $grant || ! $code || $client->id !== $grant->client_id || $code->client_id !== $clientId || $code->redirect_uri !== $redirect || $code->resource !== $this->metadata->resource() || $grant->resource !== $code->resource || $grant->issuer !== $this->metadata->issuer()) {
                return new OAuthError('invalid_grant');
            }
            $tokens = PersonalAccessToken::where('grant_id', $grant->id)->orderBy('id')->lockForUpdate()->get();
            if ($code->consumed_at !== null) {
                $tokens->where('authorization_code_id', $code->id)->each(fn ($token) => $token->update(['revoked_at' => now()]));

                return new OAuthError('invalid_grant');
            }
            if (! $account || ! $member || ! in_array($member->role, ['admin', 'read-only', 'restricted'], true) || $grant->revoked_at !== null || ! $grant->expires_at->isFuture() || ! $code->expires_at->isFuture() || $code->verifier_attempts >= 5) {
                return new OAuthError('invalid_grant');
            }
            foreach ($grant->scopes as $scope) {
                if (! Permissions::allows($member, $scope)) {
                    return new OAuthError('invalid_grant');
                }
            }
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            if (! hash_equals($code->code_challenge, $challenge)) {
                $code->increment('verifier_attempts');

                return new OAuthError('invalid_grant');
            }
            $code->update(['consumed_at' => now()]);
            $secret = Str::random(64);
            $expiry = $grant->expires_at->min(now()->addHour());
            $token = new PersonalAccessToken;
            $token->forceFill(['tokenable_type' => User::class, 'tokenable_id' => $grant->user_id, 'name' => 'MCP', 'token' => hash('sha256', $secret), 'abilities' => $grant->scopes, 'purpose' => 'mcp', 'account_id' => $grant->account_id, 'client_id' => $clientId, 'grant_id' => $grant->id, 'authorization_code_id' => $code->id, 'issuer' => $grant->issuer, 'resource' => $grant->resource, 'expires_at' => $expiry])->save();
            $client->update(['last_used_at' => now(), 'expires_at' => $client->dynamic ? now()->addDays(30) : null]);

            return ['access_token' => $token->id.'|'.$secret, 'token_type' => 'Bearer', 'expires_in' => max(1, (int) now()->diffInSeconds($expiry)), 'scope' => implode(' ', $grant->scopes)];
        }, 3);
        if ($result instanceof OAuthError) {
            throw $result;
        }

        return $result;
    }

    public function revoke(array $input): void
    {
        $this->metadata->issuer();
        $client = $this->client(Input::clientId($input));
        $raw = Input::string($input, 'token', 1, 512);
        // Unknown/foreign tokens are indistinguishable; hint never prevents revocation.
        $token = PersonalAccessToken::findToken($raw);
        if ($token && $token->purpose === 'mcp' && $token->client_id === $client->id) {
            $grant = McpGrant::find($token->grant_id);
            if ($grant && $grant->user_id !== null && $grant->issuer === $this->metadata->issuer() && $grant->resource === $this->metadata->resource()) {
                app(GrantService::class)->revoke($grant->id, $grant->user_id, 'oauth.revoke');
            }
        }
    }
}
