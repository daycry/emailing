<?php

use App\Http\Controllers\SendingApiController;
use App\Http\Middleware\AccountBearer;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(AccountBearer::class)->group(function () {
    Route::post('mail/send', SendingApiController::class)->name('api.mail.send');
    Route::get('mail/requests/{requestId}', SendingApiController::class)->name('api.mail.status');
    Route::get('keys', SendingApiController::class)->name('api.keys.list');
    Route::post('keys', SendingApiController::class)->name('api.keys.create');
    Route::delete('keys/{keyId}', SendingApiController::class)->name('api.keys.revoke');
});
