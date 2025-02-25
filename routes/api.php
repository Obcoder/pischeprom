<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/api/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
    ->name('api.mail');
