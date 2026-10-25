<?php

namespace App\Http\Controllers\Mcp\OAuth;

use App\Services\Auth\GoogleLoginFlow;
use App\Services\Mcp\Admission;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\Mcp\OAuth\Input;
use App\Services\Mcp\OAuth\Metadata;
use App\Services\Mcp\OAuth\OAuthError;
use App\Services\Permissions;
use App\Support\TrustedUrl;
use Illuminate\Http\Request;

final class OAuthController
{
    public function __construct(private AuthorizationServer $server, private Metadata $metadata, private Admission $admission) {}

    private function peer(Request $request): string
    {
        return $this->admission->peer((string) $request->ip());
    }

    public function resource()
    {
        return response()->json($this->metadata->protectedResource());
    }

    public function metadata()
    {
        return response()->json($this->metadata->server());
    }

    public function authorize(Request $request)
    {
        $this->metadata->issuer();
        OAuthError::require($request->headers->all('authorization') === [], 'invalid_client');
        $this->admission->take([['authorize.ip', $this->peer($request), 30, 60]]);
        $input = Input::parameters($request->server('QUERY_STRING', ''));
        $pending = isset($input['pending']) ? $this->server->pending($request, Input::string($input, 'pending', 36, 36)) : $this->server->start($request, $input);
        if (! $request->user()) {
            $target = TrustedUrl::route('mcp.oauth.authorize', ['pending' => $pending->id]);
            $request->session()->put('url.intended', $target);
            app(GoogleLoginFlow::class)->rememberMcpReturn($request, $pending->id);

            return redirect(TrustedUrl::route('login'));
        }

        return response()->view('mcp.consent', ['pending' => $pending, 'client' => $this->server->client($pending->client_id), 'accounts' => $this->server->accounts($request->user()->id), 'permissions' => Permissions::SCOPES]);
    }

    public function decision(Request $request)
    {
        $this->metadata->issuer();
        OAuthError::require($request->headers->all('authorization') === [], 'invalid_client');
        if (! $request->user()) {
            return redirect(TrustedUrl::route('login'));
        }
        $this->admission->take([['authorize.ip', $this->peer($request), 30, 60]]);
        OAuthError::require($request->isMethod('POST'));
        $input = $request->post();
        if (($input['scopes'] ?? null) === '[]') {
            $input['scopes'] = [];
        }
        try {
            $url = $this->server->decision($request, $input);
        } catch (OAuthError $error) {
            if (! in_array($error->status, [403, 422], true) || ! str_contains(strtolower($request->header('Accept', '')), 'text/html')) {
                throw $error;
            }
            // The contracted HTML 200 is feedback, never a successful authorization.
            // A concurrent decision, expiry, client revocation or actor change must fail afresh.
            $pending = $this->server->pending($request, Input::string($input, 'request_id', 36, 36));

            return response()->view('mcp.consent', ['pending' => $pending, 'client' => $this->server->client($pending->client_id), 'accounts' => $this->server->accounts($request->user()->id), 'permissions' => Permissions::SCOPES, 'feedback' => true]);
        }

        return redirect()->away($url);
    }

    public function token(Request $request)
    {
        $this->metadata->issuer();
        $peer = $this->peer($request);
        try {
            $input = Input::form($request);
            $client = Input::clientId($input);
        } catch (OAuthError $error) {
            $this->admission->take([['token.ip', $peer, 30, 60]]);
            throw $error;
        }
        $this->admission->take([['token.ip', $peer, 30, 60], ['token.client', $client, 60, 60]]);

        return response()->json($this->server->token($input));
    }

    public function revoke(Request $request)
    {
        $this->metadata->issuer();
        $this->admission->take([['revoke.ip', $this->peer($request), 60, 60]]);
        $this->server->revoke(Input::form($request));

        return response('', 200);
    }

    public function register(Request $request)
    {
        $this->metadata->issuer();
        OAuthError::require(config('mcp.dynamic_registration'), 'not_found', 404);
        $peer = $this->peer($request);
        $this->admission->take([['register.ip', $peer, 5, 60], ['register.day', $peer, 20, 86400]]);
        $client = $this->server->register(Input::json($request));

        return response()->json(['client_id' => $client->id, 'client_id_issued_at' => $client->created_at->timestamp, 'client_name' => $client->name, 'redirect_uris' => $client->redirect_uris, 'token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code'], 'response_types' => ['code']], 201);
    }
}
