<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class MailProviderRawBody
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('POST') && $request->is('webhooks/mail/*')) {
            $body = stream_get_contents($request->getContent(true), 1048577);
            if (! is_string($body) || strlen($body) > 1048576) {
                return response()->json(['error' => 'Payload too large'], 413)->header('Cache-Control', 'no-store');
            }
            $request->attributes->set('mail_provider.raw_body', $body);
        }

        return $next($request);
    }
}
