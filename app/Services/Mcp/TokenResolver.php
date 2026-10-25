<?php

namespace App\Services\Mcp;

use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;

/** Dedicated MCP guard: never consults Auth, cookies, API keys or Google tokens. */
final class TokenResolver
{
    public function resolve(Request $request): array
    {
        $header = $request->header('Authorization');
        abort_unless(count($request->headers->all('authorization')) === 1 && is_string($header) && preg_match('/\ABearer ([0-9]+\|[A-Za-z0-9]{40,128})\z/Di', $header, $match), 401);
        $token = PersonalAccessToken::findToken($match[1]);
        abort_unless($token instanceof PersonalAccessToken, 401);

        return [$token, DelegatedContext::verified($token)];
    }
}
