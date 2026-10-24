<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CronicleToken
{
    public function __construct(private Repository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expected = $this->config->get('cronicle.secret');
        if (! is_string($expected) || preg_match('/\A[a-f0-9]{64}\z/D', $expected) !== 1) {
            return new JsonResponse(['error' => 'scheduler_disabled'], 503, ['Cache-Control' => 'no-store']);
        }

        $authorization = $request->header('Authorization');
        if (! is_string($authorization) || strlen($authorization) > 71 || preg_match('/\A(?i:Bearer) ([a-f0-9]{64})\z/D', $authorization, $matches) !== 1 || ! hash_equals($expected, $matches[1])) {
            return new JsonResponse(['error' => 'unauthorized'], 401, ['WWW-Authenticate' => 'Bearer', 'Cache-Control' => 'no-store']);
        }

        if (! $request->isMethod('POST')) {
            return new JsonResponse(['error' => 'post_required'], 405, ['Allow' => 'POST', 'Cache-Control' => 'no-store']);
        }
        if ($request->query->count() !== 0) {
            return new JsonResponse(['error' => 'invalid_input'], 422, ['Cache-Control' => 'no-store']);
        }

        return $next($request);
    }
}
