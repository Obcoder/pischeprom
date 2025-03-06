<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('/products', \App\Http\Controllers\API\ProductController::class)
    ->name('index', 'api.products');
Route::apiResource('/buildings', \App\Http\Controllers\API\BuildingController::class)
    ->name('index', 'api.buildings');

Route::post('/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
    ->name('api.mail');
