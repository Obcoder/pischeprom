<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StageController;
use App\Http\Controllers\API\TelephoneController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('buildings', BuildingController::class)
    ->names(
        [
            'index' => 'api.buildings.index',
            'show' => 'api.buildings.show',
            'store' => 'api.buildings.store',
            'update' => 'api.buildings.update',
            'destroy' => 'api.buildings.destroy',
        ]
    );
Route::apiResource('cities', CityController::class)
    ->names(
        [
            'index' => 'api.cities.index',
            'show' => 'api.cities.show',
            'store' => 'api.city.store',
            'update' => 'api.city.update',
            'destroy' => 'api.city.destroy',
        ]
    );
Route::apiResource('goods', GoodController::class)
    ->names([
                'index' => 'api.goods.index',
                'show'  => 'api.goods.show',
                'store' => 'api.goods.store',
                'update' => 'api.goods.update',
                'destroy' => 'api.goods.destroy'
            ]);
Route::apiResource('products', ProductController::class)
    ->names(
        [
            'index' => 'api.products.index',
            'show'  => 'api.products.show',
            'store' => 'api.products.store',
            'update' => 'api.products.update',
            'destroy' => 'api.products.destroy',
        ]
    );
Route::apiResource('sales', \App\Http\Controllers\API\SaleController::class)->names(
    [
        'index' => 'api.sales.index',
        'show'  => 'api.sales.show',
        'store' => 'api.sales.store',
        'update' => 'api.sales.update',
        'destroy' => 'api.sales.destroy',
    ]);
Route::apiResource('stages', StageController::class)
    ->names(
        [
            'index' => 'api.stages.index',
            'show'  => 'api.stages.show',
            'store' => 'api.stages.store',
            'update' => 'api.stages.update',
            'destroy' => 'api.stages.destroy',
        ]
    );
Route::apiResource('telephones', TelephoneController::class)
    ->names(
        [
            'index' => 'api.telephones.index',
            'show'  => 'api.telephones.show',
            'store' => 'api.telephones.store',
            'update' => 'api.telephones.update',
            'destroy' => 'api.telephones.destroy',
        ]
    );

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
