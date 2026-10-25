<?php

use App\Http\Controllers\Mcp\OAuth\OAuthController;
use App\Http\Middleware\McpOAuthBoundary;
use Illuminate\Support\Facades\Route;

Route::middleware(McpOAuthBoundary::class)->group(function (): void {
    Route::get('/.well-known/oauth-protected-resource/mcp', [OAuthController::class, 'resource']);
    Route::get('/.well-known/oauth-protected-resource', [OAuthController::class, 'resource']);
    Route::get('/.well-known/oauth-authorization-server', [OAuthController::class, 'metadata']);
    Route::post('/oauth/token', [OAuthController::class, 'token'])->name('mcp.oauth.token');
    Route::post('/oauth/revoke', [OAuthController::class, 'revoke'])->name('mcp.oauth.revoke');
    Route::post('/oauth/register', [OAuthController::class, 'register'])->name('mcp.oauth.register');
    Route::middleware('web')->group(function (): void {
        Route::get('/oauth/authorize', [OAuthController::class, 'authorize'])->name('mcp.oauth.authorize');
        Route::post('/oauth/authorize/decision', [OAuthController::class, 'decision'])->name('mcp.oauth.decision');
    });
});
