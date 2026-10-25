<?php

namespace App\Http\Middleware;

use App\Services\Mcp\OAuth\OAuthError;
use App\Support\ApiNoStoreHeaders;
use App\Support\TrustedUrl;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

final class McpOAuthBoundary
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (OAuthError $error) {
            $response = $error->toResponse($request);
        } catch (AuthenticationException) {
            $response = redirect(TrustedUrl::route('login'));
        } catch (TokenMismatchException) {
            $response = response('La sesión ha caducado. Vuelve a iniciar la conexión.', 419);
        } catch (\Throwable) {
            // No debug renderer, request reflection, SQL bindings or secret diagnostics.
            $response = response()->json(['error' => 'temporarily_unavailable'], 503);
        }
        // Laravel's inner routing pipeline may render an exception before it bubbles here.
        // Replace that rendered body too, including when APP_DEBUG is enabled.
        if ($response->getStatusCode() >= 500) {
            $response = response()->json(['error' => 'temporarily_unavailable'], 503);
        } elseif ($response->getStatusCode() === 419) {
            $response = response('La sesión ha caducado. Vuelve a iniciar la conexión.', 419);
        }
        $response->headers = new ApiNoStoreHeaders($response->headers->all());
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
