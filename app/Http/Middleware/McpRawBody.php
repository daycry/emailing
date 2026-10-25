<?php

namespace App\Http\Middleware;

use App\Services\Mcp\Admission;
use App\Services\Mcp\OAuth\OAuthError;
use App\Services\Mcp\ProtocolError;
use App\Support\ApiNoStoreHeaders;
use App\Support\StrictMcpJson;
use Closure;
use Illuminate\Http\Request;

/** Runs before Laravel's JSON-normalizing global middleware. */
final class McpRawBody
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->is('mcp')) {
            return $next($request);
        }
        try {
            if (! config('mcp.enabled')) {
                throw new ProtocolError(404, -32600, 'Endpoint unavailable');
            }
            // This global boundary precedes TrustProxies; use the actual socket peer,
            // never a forwarded header or a trusted-proxy state left by a prior request.
            app(Admission::class)->take([['transport-ip', app(Admission::class)->peer((string) $request->server('REMOTE_ADDR')), 60, 60]]);
            if ($request->isMethod('POST')) {
                ProtocolError::require(strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0])) === 'application/json', 415);
                $stream = $request->getContent(true);
                $bytes = stream_get_contents($stream, 4194305);
                ProtocolError::require(is_string($bytes) && strlen($bytes) <= 4194304, 413);
                $request->attributes->set('mcp.ast', StrictMcpJson::decode($bytes));
            }

            return $next($request);
        } catch (OAuthError $error) {
            return $this->error(new ProtocolError(429, -32000, 'Rate limited'), $error->retryAfter);
        } catch (ProtocolError $error) {
            return $this->error($error);
        } catch (\Throwable $error) {
            report($error);

            return $this->error(new ProtocolError(503, -32603, 'Temporarily unavailable'));
        }
    }

    private function error(ProtocolError $error, ?int $retry = null): mixed
    {
        $response = response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $error->rpcCode, 'message' => $error->safeMessage]], $error->status);
        $response->headers = new ApiNoStoreHeaders($response->headers->all());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($retry !== null) {
            $response->headers->set('Retry-After', (string) $retry);
        }

        return $response;
    }
}
