<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvitoController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\CatalogController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\CommodityController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\EmailController;
use App\Http\Controllers\API\EntityController;
use App\Http\Controllers\API\EntitiesClassification;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\GoodSaleController;
use App\Http\Controllers\API\LabelController;
use App\Http\Controllers\API\MeasureController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\PlantController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\SegmentController;
use App\Http\Controllers\API\SendingController;
use App\Http\Controllers\API\StageController;
use App\Http\Controllers\API\TelephoneController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UriController;
use App\Http\Controllers\API\YandexRequestController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('brands', BrandController::class);
Route::apiResource('buildings', BuildingController::class);
Route::apiResource('catalogs', CatalogController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('checks', CheckController::class);
Route::apiResource('cities', CityController::class);
Route::apiResource('commodities', CommodityController::class);
Route::apiResource('countries', CountryController::class);
Route::apiResource('currencies', CurrencyController::class);
Route::apiResource('emails', EmailController::class);
Route::apiResource('entities', EntityController::class);
Route::apiResource('entities-classification', EntitiesClassification::class);
Route::apiResource('genera', GenusController::class);
Route::apiResource('goods', GoodController::class);
Route::get('/goods/{id}/{slug?}', [GoodController::class, 'show'])
    ->where('id', '[0-9]+')->name('good.fetch');
Route::apiResource('goodsales', GoodSaleController::class);
Route::apiResource('labels', LabelController::class);
Route::apiResource('measures', MeasureController::class);
Route::apiResource('messages', MessageController::class);
Route::apiResource('plants', PlantController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('purchases', PurchaseController::class);
Route::apiResource('regions', RegionController::class);
Route::apiResource('sales', SaleController::class);
Route::apiResource('segments', SegmentController::class);
Route::apiResource('sendings', SendingController::class);
Route::apiResource('stages', StageController::class);
Route::apiResource('telephones', TelephoneController::class);
Route::apiResource('units', UnitController::class);
Route::apiResource('uris', UriController::class);
Route::apiResource('yandex-requests', YandexRequestController::class);

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

/*
 * -------------------------
 * A V I T O
 * -------------------------
 */
Route::get('/avito/user', [AvitoController::class, 'getUserInfo']);
