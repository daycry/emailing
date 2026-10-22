<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        // Fortify canonicalizes registration input before calling our action.
        // The login throttle also runs before its form request validation.
        if ($request->isMethod('POST') && $request->routeIs('register.store', 'login.store')) {
            $request->validate(['email' => ['required', 'string']]);
        }

        return $next($request);
    }
}
