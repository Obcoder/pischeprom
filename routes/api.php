<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
    ->name('api.mail');

/*
 * ------------------
 *  T E L E G R A M
 * __________________
 */
Route::post('/webhook', [TelegramController::class, 'webhook']);
Route::post('/telegram/send-message/{chat?}/{text?}', [TelegramController::class, 'sendMessage'])
    ->name('api.telegram.sendMessage');

/*
 * --------------------------
 * S T O R A G E
 * --------------------------
 */
Route::get('/units/{name}/files', [\App\Http\Controllers\API\UnitController::class, 'getUnitFiles'])
    ->name('api.unit.getFiles');
