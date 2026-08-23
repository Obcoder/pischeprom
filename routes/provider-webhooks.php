<?php

use App\Http\Controllers\Public\UnisenderWebhookController;
use App\Http\Middleware\ThrottleVerifiedUnisenderWebhookRequest;
use App\Http\Middleware\VerifyUnisenderWebhookRequest;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/unisender-go', [UnisenderWebhookController::class, 'verify'])
    ->name('webhooks.unisender-go.verify');

Route::post('/webhooks/unisender-go', [UnisenderWebhookController::class, 'handle'])
    ->middleware([
        VerifyUnisenderWebhookRequest::class,
        ThrottleVerifiedUnisenderWebhookRequest::class,
    ])
    ->name('webhooks.unisender-go.handle');
