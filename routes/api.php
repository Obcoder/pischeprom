<?php

use App\Http\Controllers\API\PriceTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvitoController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\BeelinePbxController;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\BuildingTypeController;
use App\Http\Controllers\API\CatalogController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\CityPopulationController;
use App\Http\Controllers\API\CommodityController;
use App\Http\Controllers\API\CommodityMediaController;
use App\Http\Controllers\API\ComponentController;
use App\Http\Controllers\API\CountryController;
use App\Http\Controllers\API\CurrencyController;
use App\Http\Controllers\API\EmailController;
use App\Http\Controllers\API\EmailMailboxController;
use App\Http\Controllers\API\EmailRelationController;
use App\Http\Controllers\API\EntityController;
use App\Http\Controllers\API\EntityMetaController;
use App\Http\Controllers\API\EntitiesClassification;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\API\FragranceController;
use App\Http\Controllers\API\IndustryController;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\GoodPriceCalculationController;
use App\Http\Controllers\API\GoodPriceTypeValueController;
use App\Http\Controllers\API\GoodMediaController;
use App\Http\Controllers\API\GoodMediaFolderController;
use App\Http\Controllers\API\GoodSeoController;
use App\Http\Controllers\API\GoodSaleController;
use App\Http\Controllers\API\LabelController;
use App\Http\Controllers\API\LeadController;
use App\Http\Controllers\API\MeasureController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\NoteController;
use App\Http\Controllers\API\PlantController;
use App\Http\Controllers\API\PhoneCallController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductSearchController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\SegmentController;
use App\Http\Controllers\API\SendingController;
use App\Http\Controllers\API\StageController;
use App\Http\Controllers\API\SupplierPipelineCardController;
use App\Http\Controllers\API\SupplierPipelineController;
use App\Http\Controllers\API\SupplierPipelineStageController;
use App\Http\Controllers\API\SupplierWorkBoardController;
use App\Http\Controllers\API\TelephoneController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UnitController as ApiUnitController;
use App\Http\Controllers\API\UnitRelationController;
use App\Http\Controllers\API\UnitFileController;
use App\Http\Controllers\API\UnitMailController;
use App\Http\Controllers\API\UriController;
use App\Http\Controllers\API\YandexRequestController;
use App\Http\Controllers\API\Marketing\YandexAccountController;
use App\Http\Controllers\API\Marketing\YandexDirectAdController;
use App\Http\Controllers\API\Marketing\YandexDirectGoodController;
use App\Http\Controllers\API\Marketing\YandexDirectLaunchController;
use App\Http\Controllers\API\Marketing\YandexDirectStatsController;
use App\Http\Controllers\API\Marketing\YandexOAuthController;
use App\Http\Controllers\API\Marketing\YandexSyncLogController;
use App\Services\YandexSearchService;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



/*
 * ------------------
 *  C I T I E S
 * __________________
 */
Route::apiResource('cities', CityController::class);
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
//  E N D  C I T I E S



/*
|--------------------------------------------------------------------------
| C O M M O D I T I E S
|--------------------------------------------------------------------------
*/
Route::prefix('commodities/{commodity}')
    ->name('api.commodities.')
    ->group(function () {
        Route::get('/media', [CommodityMediaController::class, 'index'])
            ->name('media.index');

        Route::post('/media', [CommodityMediaController::class, 'store'])
            ->name('media.store');

        Route::patch('/media/{media}/rename', [CommodityMediaController::class, 'rename'])
            ->name('media.rename');

        Route::patch('/media/{media}/ava', [CommodityMediaController::class, 'setAva'])
            ->name('media.ava');

        Route::delete('/media/{media}', [CommodityMediaController::class, 'destroy'])
            ->name('media.destroy');
    });

Route::apiResource('commodities', CommodityController::class);

//  E N D  C O M M O D I T I E S



//  E M A I L S
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
Route::get('mail-messages/folders', [MailMessageController::class, 'folders'])
    ->name('mail-messages.folders');
Route::get('mail-messages', [MailMessageController::class, 'index'])
    ->name('mail-messages.index');

Route::get('mail-messages/{mailMessage}', [MailMessageController::class, 'show'])
    ->name('mail-messages.show');

use App\Http\Controllers\API\MailTemplateController;
Route::apiResource('mail-templates', MailTemplateController::class)
    ->except(['show']);
// E N D  E M A I L S



/*
 * ------------------
 *  E N T I T I E S
 * __________________
 */
Route::apiResource('entities', EntityController::class)->only(['store', 'update', 'destroy']);
Route::get('/entities-meta', [EntityMetaController::class, 'index']);
Route::apiResource('entities', EntityController::class);

//  E N D  E N T I T I E S



/*
 * ----------------------
 *  G O O D S
 * ______________________
 */
Route::apiResource('goods', GoodController::class)->except(['show']);

Route::prefix('goods/{good}')
    ->name('api.goods.')
    ->group(function () {
        Route::get('/price-calculations', [GoodPriceCalculationController::class, 'index'])
            ->name('price-calculations.index');

        Route::post('/price-calculations', [GoodPriceCalculationController::class, 'store'])
            ->name('price-calculations.store');

        Route::patch('/price-calculations/{calculation}', [GoodPriceCalculationController::class, 'update'])
            ->name('price-calculations.update');

        Route::delete('/price-calculations/{calculation}', [GoodPriceCalculationController::class, 'destroy'])
            ->name('price-calculations.destroy');

        Route::get('/seo', [GoodSeoController::class, 'show'])
            ->name('seo.show');

        Route::put('/seo', [GoodSeoController::class, 'upsert'])
            ->name('seo.upsert');

        Route::patch('/seo', [GoodSeoController::class, 'upsert'])
            ->name('seo.patch');

        Route::post('/seo/generate-structured-data', [GoodSeoController::class, 'generateStructuredData'])
            ->name('seo.generate-structured-data');

        Route::get('/price-type-values', [GoodPriceTypeValueController::class, 'index'])
            ->name('price-type-values.index');

        Route::post('/price-type-values', [GoodPriceTypeValueController::class, 'store'])
            ->name('price-type-values.store');

        Route::patch('/price-type-values/{value}', [GoodPriceTypeValueController::class, 'update'])
            ->name('price-type-values.update');

        Route::delete('/price-type-values/{value}', [GoodPriceTypeValueController::class, 'destroy'])
            ->name('price-type-values.destroy');

        Route::get('/media-folders', [GoodMediaFolderController::class, 'index'])
            ->name('media-folders.index');

        Route::post('/media-folders', [GoodMediaFolderController::class, 'store'])
            ->name('media-folders.store');

        Route::patch('/media-folders/{folder}', [GoodMediaFolderController::class, 'update'])
            ->name('media-folders.update');

        Route::delete('/media-folders/{folder}', [GoodMediaFolderController::class, 'destroy'])
            ->name('media-folders.destroy');

        Route::get('/media', [GoodMediaController::class, 'index'])
            ->name('media.index');

        Route::post('/media', [GoodMediaController::class, 'store'])
            ->name('media.store');

        Route::patch('/media/{media}', [GoodMediaController::class, 'update'])
            ->name('media.update');

        Route::patch('/media/{media}/rename', [GoodMediaController::class, 'rename'])
            ->name('media.rename');

        Route::patch('/media/{media}/ava', [GoodMediaController::class, 'setAva'])
            ->name('media.ava');

        Route::patch('/media/{media}/publish', [GoodMediaController::class, 'togglePublish'])
            ->name('media.publish');

        Route::delete('/media/{media}', [GoodMediaController::class, 'destroy'])
            ->name('media.destroy');

        Route::patch('/media/{media}/process', [GoodMediaController::class, 'processVideo'])
            ->name('media.process');

        Route::patch('/media/{media}/main-video', [GoodMediaController::class, 'setMainVideo'])
            ->name('media.main-video');
    });

Route::get('/goods/{id}/{slug?}', [GoodController::class, 'show'])
    ->where('id', '[0-9]+')->name('good.fetch');

Route::patch('goods/{good}/publish', [GoodController::class, 'togglePublish'])
    ->name('api.goods.publish');
//  E N D  G O O D S



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
//  E N D  T E L E P H O N E S



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

    Route::post('/industries', [UnitRelationController::class, 'attachIndustry'])->name('api.units.industries.attach');
    Route::delete('/industries/{industry}', [UnitRelationController::class, 'detachIndustry'])->name('api.units.industries.detach');

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
    Route::post('/files/folders', [UnitFileController::class, 'storeFolder'])->name('api.units.files.folders.store');
    Route::patch('/files/move', [UnitFileController::class, 'move'])->name('api.units.files.move');
    Route::delete('/files', [UnitFileController::class, 'destroy'])->name('api.units.files.destroy');
    Route::patch('/files/rename', [UnitFileController::class, 'rename'])->name('api.units.files.rename');

    Route::get('/mail-messages', [UnitMailController::class, 'index'])
        ->name('api.units.mail-messages.index');

    Route::post('/mail/send', [UnitMailController::class, 'send'])
        ->name('api.units.mail.send');
});

Route::apiResource('units', UnitController::class)->except(['show']);

// E N D  U N I T S


Route::apiResource('brands', BrandController::class);
Route::apiResource('buildings', BuildingController::class);
Route::apiResource('building-types', BuildingTypeController::class)
    ->parameters(['building-types' => 'buildingType']);
Route::apiResource('catalogs', CatalogController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('checks', CheckController::class);
Route::apiResource('components', ComponentController::class);
Route::apiResource('countries', CountryController::class);
Route::apiResource('currencies', CurrencyController::class);
Route::apiResource('entities-classification', EntitiesClassification::class);
Route::apiResource('fields', FieldController::class);
Route::apiResource('fragrances', FragranceController::class);
Route::apiResource('industries', IndustryController::class);
// если нужно быстро получить units по industry:
Route::get('industries/{industry}/units', [IndustryController::class, 'units']);
Route::apiResource('genera', GenusController::class);
Route::apiResource('goodsales', GoodSaleController::class);
Route::apiResource('labels', LabelController::class);
Route::apiResource('measures', MeasureController::class);
Route::apiResource('messages', MessageController::class);
Route::apiResource('notes', NoteController::class);
Route::apiResource('plants', PlantController::class);
Route::apiResource('price-types', PriceTypeController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('purchases', PurchaseController::class);
Route::apiResource('quotations', QuotationController::class);
Route::apiResource('regions', RegionController::class);
Route::apiResource('sales', SaleController::class);
Route::apiResource('segments', SegmentController::class);
Route::apiResource('sendings', SendingController::class);
Route::apiResource('stages', StageController::class);
Route::apiResource('uris', UriController::class);
Route::get('/vat-rates', [GoodController::class, 'vatRates'])->name('api.vat-rates');
Route::apiResource('yandex-requests', YandexRequestController::class);

Route::prefix('marketing')
    ->name('api.marketing.')
    ->group(function () {
        Route::get('/yandex/accounts', [YandexAccountController::class, 'index'])
            ->name('yandex.accounts.index');
        Route::patch('/yandex/accounts/{account}', [YandexAccountController::class, 'update'])
            ->name('yandex.accounts.update');
        Route::delete('/yandex/accounts/{account}', [YandexAccountController::class, 'destroy'])
            ->name('yandex.accounts.destroy');
        Route::post('/yandex/accounts/{account}/check', [YandexAccountController::class, 'check'])
            ->name('yandex.accounts.check');
        Route::post('/yandex/oauth/exchange-code', [YandexOAuthController::class, 'exchangeVerificationCode'])
            ->name('yandex.oauth.exchange-code');

        Route::get('/direct/goods', [YandexDirectGoodController::class, 'index'])
            ->name('direct.goods.index');
        Route::post('/direct/goods/{good}/generate-draft', [YandexDirectGoodController::class, 'generateDraft'])
            ->name('direct.goods.generate-draft');
        Route::get('/direct/launch-dashboard', [YandexDirectLaunchController::class, 'dashboard'])
            ->name('direct.launch.dashboard');
        Route::get('/direct/launch-sessions', [YandexDirectLaunchController::class, 'sessions'])
            ->name('direct.launch.sessions');
        Route::post('/direct/launch/{good}', [YandexDirectLaunchController::class, 'launch'])
            ->name('direct.launch');

        Route::get('/direct/ads', [YandexDirectAdController::class, 'index'])
            ->name('direct.ads.index');
        Route::get('/direct/ads/{ad}', [YandexDirectAdController::class, 'show'])
            ->name('direct.ads.show');
        Route::put('/direct/ads/{ad}', [YandexDirectAdController::class, 'update'])
            ->name('direct.ads.update');
        Route::post('/direct/ads/{ad}/validate', [YandexDirectAdController::class, 'validateAd'])
            ->name('direct.ads.validate');
        Route::post('/direct/ads/{ad}/send', [YandexDirectAdController::class, 'send'])
            ->name('direct.ads.send');
        Route::post('/direct/ads/{ad}/suspend', [YandexDirectAdController::class, 'suspend'])
            ->name('direct.ads.suspend');
        Route::post('/direct/ads/{ad}/resume', [YandexDirectAdController::class, 'resume'])
            ->name('direct.ads.resume');

        Route::get('/direct/stats', [YandexDirectStatsController::class, 'index'])
            ->name('direct.stats.index');
        Route::post('/direct/stats/sync', [YandexDirectStatsController::class, 'sync'])
            ->name('direct.stats.sync');

        Route::get('/direct/logs', [YandexSyncLogController::class, 'index'])
            ->name('direct.logs.index');
    });


/*
 * ------------------
 *  T E L E P H O N Y
 * __________________
 */
Route::post('/telephony/beeline', BeelinePbxController::class)
    ->name('api.telephony.beeline');

Route::post('phone-calls/dial', [PhoneCallController::class, 'dial'])
    ->name('api.phone-calls.dial');
Route::apiResource('phone-calls', PhoneCallController::class)
    ->only(['index', 'store', 'show', 'update']);
Route::post('phone-calls/sync-beeline', [PhoneCallController::class, 'syncBeeline'])
    ->name('api.phone-calls.sync-beeline');

/*
 * ----------------------------
 *  S U P P L I E R  W O R K
 * ____________________________
 */
Route::prefix('supplier-work')
    ->name('api.supplier-work.')
    ->group(function () {
        Route::get('/board', [SupplierWorkBoardController::class, 'index'])->name('board');
        Route::get('/unit-options', [SupplierWorkBoardController::class, 'unitOptions'])->name('unit-options');

        Route::post('/pipelines', [SupplierPipelineController::class, 'store'])->name('pipelines.store');
        Route::patch('/pipelines/{pipeline}', [SupplierPipelineController::class, 'update'])->name('pipelines.update');
        Route::delete('/pipelines/{pipeline}', [SupplierPipelineController::class, 'destroy'])->name('pipelines.destroy');

        Route::post('/pipelines/{pipeline}/stages', [SupplierPipelineStageController::class, 'store'])->name('stages.store');
        Route::patch('/pipelines/{pipeline}/stages/reorder', [SupplierPipelineStageController::class, 'reorder'])->name('stages.reorder');
        Route::patch('/stages/{stage}', [SupplierPipelineStageController::class, 'update'])->name('stages.update');
        Route::delete('/stages/{stage}', [SupplierPipelineStageController::class, 'destroy'])->name('stages.destroy');

        Route::post('/pipelines/{pipeline}/cards', [SupplierPipelineCardController::class, 'store'])->name('cards.store');
        Route::patch('/cards/{card}', [SupplierPipelineCardController::class, 'update'])->name('cards.update');
        Route::patch('/cards/{card}/move', [SupplierPipelineCardController::class, 'move'])->name('cards.move');
        Route::delete('/cards/{card}', [SupplierPipelineCardController::class, 'destroy'])->name('cards.destroy');
    });
Route::post('phone-calls/{phoneCall}/create-entity', [PhoneCallController::class, 'createEntity'])
    ->name('api.phone-calls.create-entity');

Route::apiResource('leads', LeadController::class)
    ->only(['index', 'show', 'update']);
//  E N D  T E L E P H O N Y


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
