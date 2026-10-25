<?php

use App\Http\Middleware\McpRawBody;
use App\Http\Middleware\ValidateAuthEmail;
use App\Services\Mcp\OAuth\OAuthError;
use App\Support\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/internal.php';
            require __DIR__.'/../routes/api.php';
            require __DIR__.'/../routes/mcp-oauth.php';
            require __DIR__.'/../routes/ai.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(McpRawBody::class);
        $middleware->trimStrings([fn ($request) => $request->is('mcp')]);
        $middleware->convertEmptyStringsToNull([fn ($request) => $request->is('mcp')]);
        $middleware->prependToPriorityList(ThrottleRequests::class, ValidateAuthEmail::class);
        $middleware->authenticateSessions();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiError::class, OAuthError::class]);
    })->create();
