<?php

use App\Http\Controllers\MailProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/mail/{provider}/{connection}', MailProviderWebhookController::class)
    ->where('provider', 'brevo|sendgrid')->whereUuid('connection')->middleware('throttle:120,1')
    ->name('mail-provider.webhook');
