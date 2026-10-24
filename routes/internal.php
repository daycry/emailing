<?php

use App\Http\Controllers\SchedulerTriggerController;
use App\Http\Middleware\CronicleToken;
use Illuminate\Support\Facades\Route;

// Loaded separately from the web/session/CSRF group; only the dedicated token
// authorizes this global infrastructure action. A teammate login grants none.
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'], '/internal/cronicle/trigger', SchedulerTriggerController::class)
    ->middleware(CronicleToken::class)
    ->name('internal.cronicle.trigger');
