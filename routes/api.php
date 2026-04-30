<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvitoController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\CatalogController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\CommodityController;
use App\Http\Controllers\API\ComponentController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\EntityController;
use App\Http\Controllers\API\EntityMetaController;
use App\Http\Controllers\API\EntitiesClassification;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\API\FragranceController;
use App\Http\Controllers\API\IndustryController;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\GoodSaleController;
use App\Http\Controllers\API\LabelController;
use App\Http\Controllers\API\MeasureController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\NoteController;
use App\Http\Controllers\API\PlantController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductSearchController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\SegmentController;
use App\Http\Controllers\API\SendingController;
use App\Http\Controllers\API\StageController;
use App\Http\Controllers\API\TelephoneController;

use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UnitController as ApiUnitController;
use App\Http\Controllers\API\UnitRelationController;
use App\Http\Controllers\API\UnitFileController;

use App\Http\Controllers\API\UriController;
use App\Http\Controllers\API\YandexRequestController;
use App\Services\YandexSearchService;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('brands', BrandController::class);
Route::apiResource('buildings', BuildingController::class);
Route::apiResource('catalogs', CatalogController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('checks', CheckController::class);

//  C I T I E S
use App\Http\Controllers\API\CityController;
Route::apiResource('cities', CityController::class);
use App\Http\Controllers\API\CityPopulationController;
Route::prefix('cities/{city}')
    ->name('cities.')
    ->group(function () {
        Route::get('/populations', [CityPopulationController::class, 'index'])
            ->name('populations.index');

        Route::post('/populations', [CityPopulationController::class, 'store'])
            ->name('populations.store');

        Route::put('/populations/{cityPopulation}', [CityPopulationController::class, 'update'])
            ->name('populations.update');

        Route::patch('/populations/{cityPopulation}', [CityPopulationController::class, 'update'])
            ->name('populations.patch');

        Route::delete('/populations/{cityPopulation}', [CityPopulationController::class, 'destroy'])
            ->name('populations.destroy');
    });
//

Route::apiResource('commodities', CommodityController::class);
Route::apiResource('components', ComponentController::class);
Route::apiResource('countries', CountryController::class);
Route::apiResource('currencies', CurrencyController::class);

//  E M A I L S
use App\Http\Controllers\API\EmailController;
use App\Http\Controllers\API\EmailMailboxController;
use App\Http\Controllers\API\EmailRelationController;
Route::get('emails/meta', [EmailController::class, 'meta'])
    ->name('emails.meta');

Route::post('emails/sync-yandex', [EmailMailboxController::class, 'sync'])
    ->name('emails.sync-yandex');

Route::get('emails/{email}/mailbox', [EmailMailboxController::class, 'show'])
    ->name('emails.mailbox');

Route::post('emails/{email}/units/sync', [EmailRelationController::class, 'syncUnits'])
    ->name('emails.units.sync');

Route::post('emails/{email}/entities/sync', [EmailRelationController::class, 'syncEntities'])
    ->name('emails.entities.sync');

Route::apiResource('emails', EmailController::class);

use App\Http\Controllers\API\MailMessageController;
Route::get('mail-messages', [MailMessageController::class, 'index'])
    ->name('mail-messages.index');

Route::get('mail-messages/{mailMessage}', [MailMessageController::class, 'show'])
    ->name('mail-messages.show');
// E N D  E M A I L S

Route::get('/entities-meta', [EntityMetaController::class, 'index']);
Route::apiResource('entities', EntityController::class);

Route::apiResource('entities-classification', EntitiesClassification::class);
Route::apiResource('fields', FieldController::class);
Route::apiResource('fragrances', FragranceController::class);
Route::apiResource('industries', IndustryController::class);
// если нужно быстро получить units по industry:
Route::get('industries/{industry}/units', [IndustryController::class, 'units']);
Route::apiResource('genera', GenusController::class);
Route::apiResource('goods', GoodController::class)->except(['show']);
Route::get('/goods/{id}/{slug?}', [GoodController::class, 'show'])
    ->where('id', '[0-9]+')->name('good.fetch');
Route::patch('goods/{good}/publish', [GoodController::class, 'togglePublish'])
    ->name('api.goods.publish');
Route::apiResource('goodsales', GoodSaleController::class);
Route::apiResource('labels', LabelController::class);
Route::apiResource('measures', MeasureController::class);
Route::apiResource('messages', MessageController::class);
Route::apiResource('notes', NoteController::class);
Route::apiResource('plants', PlantController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('purchases', PurchaseController::class);
Route::apiResource('quotations', QuotationController::class);
Route::apiResource('regions', RegionController::class);
Route::apiResource('sales', SaleController::class);
Route::apiResource('segments', SegmentController::class);
Route::apiResource('sendings', SendingController::class);
Route::apiResource('stages', StageController::class);


/*
 * ------------------
 *  E N T I T I E S
 * __________________
 */
Route::apiResource('entities', EntityController::class)->only(['store', 'update', 'destroy']);

/*
 * ----------------------
 *  T E L E P H O N E S
 * ______________________
 */
Route::prefix('telephones')->group(function () {
    Route::get('/meta', [TelephoneController::class, 'meta']);
    Route::get('/', [TelephoneController::class, 'index']);
    Route::post('/', [TelephoneController::class, 'store']);
    Route::get('/{telephone}', [TelephoneController::class, 'show']);
    Route::put('/{telephone}', [TelephoneController::class, 'update']);
});

/*
 * ------------------
 *  U N I T S
 * __________________
 */
Route::prefix('units/{unit}')->group(function () {
    Route::get('/', [ApiUnitController::class, 'show'])->name('api.units.show');

    Route::post('/uris', [UnitRelationController::class, 'attachUri'])->name('api.units.uris.attach');
    Route::delete('/uris/{uri}', [UnitRelationController::class, 'detachUri'])->name('api.units.uris.detach');

    Route::post('/telephones', [UnitRelationController::class, 'attachTelephone'])->name('api.units.telephones.attach');
    Route::delete('/telephones/{telephone}', [UnitRelationController::class, 'detachTelephone'])->name('api.units.telephones.detach');

    Route::post('/buildings', [UnitRelationController::class, 'attachBuilding'])->name('api.units.buildings.attach');
    Route::delete('/buildings/{building}', [UnitRelationController::class, 'detachBuilding'])->name('api.units.buildings.detach');

    Route::post('/labels', [UnitRelationController::class, 'attachLabel'])->name('api.units.labels.attach');
    Route::delete('/labels/{label}', [UnitRelationController::class, 'detachLabel'])->name('api.units.labels.detach');

    Route::post('/fields', [UnitRelationController::class, 'attachField'])->name('api.units.fields.attach');
    Route::delete('/fields/{field}', [UnitRelationController::class, 'detachField'])->name('api.units.fields.detach');

    Route::post('/cities', [UnitRelationController::class, 'attachCity'])
        ->name('api.units.cities.attach');

    Route::delete('/cities/{city}', [UnitRelationController::class, 'detachCity'])
        ->name('api.units.cities.detach');

    Route::post('/entities/attach', [UnitRelationController::class, 'attachEntity'])->name('api.units.entities.attach');
    Route::delete('/entities/{entity}', [UnitRelationController::class, 'detachEntity'])->name('api.units.entities.detach');

    Route::get('/files', [UnitFileController::class, 'index'])->name('api.units.files.index');
    Route::post('/files', [UnitFileController::class, 'store'])->name('api.units.files.store');
    Route::delete('/files', [UnitFileController::class, 'destroy'])->name('api.units.files.destroy');
    Route::patch('/files/rename', [UnitFileController::class, 'rename'])->name('api.units.files.rename');
});

Route::apiResource('units', UnitController::class)->except(['show']);


Route::apiResource('uris', UriController::class);

Route::get('/vat-rates', [GoodController::class, 'vatRates'])->name('api.vat-rates');

Route::apiResource('yandex-requests', YandexRequestController::class);


/*
 * -----------------------------
 *       M A I L  S E N D
 * -----------------------------
 */
Route::post('/mail', [MailController::class, 'sendMail'])
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

/*
 * ---------------------------
 * Yandex Search API
 * ---------------------------
 */

Route::prefix('products/{product}')->group(function () {
    Route::post('/yandex-search', [ProductSearchController::class, 'store']);
    Route::get('/yandex-search/latest', [ProductSearchController::class, 'latest']);
    Route::get('/yandex-search/{searchRequest}', [ProductSearchController::class, 'show']);
});
