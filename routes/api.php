<?php

use App\Http\Controllers\API\AiPriceLists\PriceListImportController as AiPriceListImportController;
use App\Http\Controllers\API\AiPriceLists\PriceListItemController as AiPriceListItemController;
use App\Http\Controllers\API\AiSales\AiAgentDefinitionController as AiSalesAgentDefinitionController;
use App\Http\Controllers\API\AiSales\AiAgentRunController as AiSalesAgentRunController;
use App\Http\Controllers\API\AiSales\AiControlPlaneController as AiSalesControlPlaneController;
use App\Http\Controllers\API\AiSales\EntityCandidateProposalController as AiSalesEntityCandidateProposalController;
use App\Http\Controllers\API\AiSales\UnitAliasController as AiSalesUnitAliasController;
use App\Http\Controllers\API\AiSales\UnitBusinessContextController as AiSalesUnitBusinessContextController;
use App\Http\Controllers\API\AiSales\UnitDossierController as AiSalesUnitDossierController;
use App\Http\Controllers\API\AiSales\UnitObservationController as AiSalesUnitObservationController;
use App\Http\Controllers\API\AiSales\UnitRoleController as AiSalesUnitRoleController;
use App\Http\Controllers\API\AiSales\UnitSourceController as AiSalesUnitSourceController;
use App\Http\Controllers\API\BeelinePbxController;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\BuildingTypeController;
use App\Http\Controllers\API\CatalogController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CheckCommodityController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\CheckServiceController;
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
use App\Http\Controllers\API\EntitiesClassification;
use App\Http\Controllers\API\EntityController;
use App\Http\Controllers\API\EntityMetaController;
use App\Http\Controllers\API\ExpenseArticleController;
use App\Http\Controllers\API\FieldBoardController;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\API\FragranceController;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\Gis\GisEntityController;
use App\Http\Controllers\API\Gis\GisRouteController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\GoodMediaController;
use App\Http\Controllers\API\GoodMediaFolderController;
use App\Http\Controllers\API\GoodPriceCalculationController;
use App\Http\Controllers\API\GoodPriceTypeValueController;
use App\Http\Controllers\API\GoodSaleController;
use App\Http\Controllers\API\GoodSeoController;
use App\Http\Controllers\API\GoodStockAlertAdminController;
use App\Http\Controllers\API\GoodStockMovementController;
use App\Http\Controllers\API\HomeBannerAssetController;
use App\Http\Controllers\API\HomeBannerController;
use App\Http\Controllers\API\IndustryController;
use App\Http\Controllers\API\LabelController;
use App\Http\Controllers\API\LeadController;
use App\Http\Controllers\API\Logistics\CheckLookupController as LogisticsCheckLookupController;
use App\Http\Controllers\API\Logistics\ExpenseCategoryController as LogisticsExpenseCategoryController;
use App\Http\Controllers\API\Logistics\LogisticsCityController;
use App\Http\Controllers\API\Logistics\LogisticsDashboardController;
use App\Http\Controllers\API\Logistics\MapConfigurationController as LogisticsMapConfigurationController;
use App\Http\Controllers\API\Logistics\MapController as LogisticsMapController;
use App\Http\Controllers\API\Logistics\MatrixController as LogisticsMatrixController;
use App\Http\Controllers\API\Logistics\MatrixPreviewController as LogisticsMatrixPreviewController;
use App\Http\Controllers\API\Logistics\ReferenceController as LogisticsReferenceController;
use App\Http\Controllers\API\Logistics\RoutingRunController as LogisticsRoutingRunController;
use App\Http\Controllers\API\Logistics\RoutingStatusController as LogisticsRoutingStatusController;
use App\Http\Controllers\API\Logistics\TripController as LogisticsTripController;
use App\Http\Controllers\API\Logistics\TripExpenseController as LogisticsTripExpenseController;
use App\Http\Controllers\API\Logistics\TripMapController as LogisticsTripMapController;
use App\Http\Controllers\API\Logistics\TripRoutingController as LogisticsTripRoutingController;
use App\Http\Controllers\API\Logistics\TripStopController as LogisticsTripStopController;
use App\Http\Controllers\API\Logistics\VehicleController as LogisticsVehicleController;
use App\Http\Controllers\API\Marketing\YandexAccountController;
use App\Http\Controllers\API\Marketing\YandexDirectAdController;
use App\Http\Controllers\API\Marketing\YandexDirectAiAutopilotController;
use App\Http\Controllers\API\Marketing\YandexDirectGeoRegionController;
use App\Http\Controllers\API\Marketing\YandexDirectGoodController;
use App\Http\Controllers\API\Marketing\YandexDirectLaunchController;
use App\Http\Controllers\API\Marketing\YandexDirectStatsController;
use App\Http\Controllers\API\Marketing\YandexOAuthController;
use App\Http\Controllers\API\Marketing\YandexSyncLogController;
use App\Http\Controllers\API\MeasureController;
use App\Http\Controllers\API\MessageController;
use App\Http\Controllers\API\NoteController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PhoneCallController;
use App\Http\Controllers\API\PlantController;
use App\Http\Controllers\API\PriceTypeController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductSearchController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\SegmentController;
use App\Http\Controllers\API\SendingController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\StageController;
use App\Http\Controllers\API\StockMovementController;
use App\Http\Controllers\API\SupplierPipelineCardController;
use App\Http\Controllers\API\SupplierPipelineController;
use App\Http\Controllers\API\SupplierPipelineStageController;
use App\Http\Controllers\API\SupplierWorkBoardController;
use App\Http\Controllers\API\TaxiShiftController;
use App\Http\Controllers\API\TelephoneController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UnitController as ApiUnitController;
use App\Http\Controllers\API\UnitFileController;
use App\Http\Controllers\API\UnitMailController;
use App\Http\Controllers\API\UnitRelationController;
use App\Http\Controllers\API\UriController;
use App\Http\Controllers\API\WarehouseController;
use App\Http\Controllers\API\YandexRequestController;
use App\Http\Controllers\AvitoAutoReplyController;
use App\Http\Controllers\AvitoController;
use App\Http\Controllers\AvitoCrmController;
use App\Http\Controllers\AvitoListingController;
use App\Http\Controllers\AvitoListingGoodController;
use App\Http\Controllers\AvitoMessageTemplateController;
use App\Http\Controllers\AvitoMessengerController;
use App\Http\Controllers\AvitoPublicationController;
use App\Http\Controllers\AvitoWorkspaceSettingsController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TelegramController;
use App\Http\Middleware\EnforceAiPriceListAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('ai-sales/units/{unit}')
    ->name('api.ai-sales.units.')
    ->middleware(['auth:sanctum', 'verified', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/dossier', AiSalesUnitDossierController::class)->name('dossier.show');

        Route::post('/roles', [AiSalesUnitRoleController::class, 'store'])->name('roles.store');
        Route::delete('/roles/{marketRole}', [AiSalesUnitRoleController::class, 'destroy'])->name('roles.destroy');

        Route::post('/contexts', [AiSalesUnitBusinessContextController::class, 'store'])->name('contexts.store');
        Route::patch('/contexts/{context}', [AiSalesUnitBusinessContextController::class, 'update'])->name('contexts.update');

        Route::post('/sources', [AiSalesUnitSourceController::class, 'store'])->name('sources.store');
        Route::post('/aliases', [AiSalesUnitAliasController::class, 'store'])->name('aliases.store');
        Route::post('/observations', [AiSalesUnitObservationController::class, 'store'])->name('observations.store');
        Route::post('/observations/{observation}/review', [AiSalesUnitObservationController::class, 'review'])->name('observations.review');
        Route::post('/observations/{observation}/promote', [AiSalesUnitObservationController::class, 'promote'])->name('observations.promote');

        Route::post('/entity-proposals', [AiSalesEntityCandidateProposalController::class, 'store'])->name('entity-proposals.store');
    });

Route::prefix('ai-sales')
    ->name('api.ai-sales.')
    ->middleware(['auth:sanctum', 'verified', 'throttle:ai-sales'])
    ->group(function (): void {
        Route::get('/control-plane', [AiSalesControlPlaneController::class, 'show'])->name('control-plane.show');
        Route::patch('/control-plane/kill-switches/{scope}', [AiSalesControlPlaneController::class, 'updateKillSwitch'])
            ->whereIn('scope', ['global', 'local_ru', 'external_sanitized'])
            ->name('control-plane.kill-switches.update');
        Route::get('/agent-definitions', [AiSalesAgentDefinitionController::class, 'index'])->name('agent-definitions.index');
        Route::get('/runs', [AiSalesAgentRunController::class, 'index'])->name('runs.index');
        Route::post('/runs', [AiSalesAgentRunController::class, 'store'])->name('runs.store');
        Route::get('/runs/{aiAgentRun}', [AiSalesAgentRunController::class, 'show'])->name('runs.show');
        Route::post('/runs/{aiAgentRun}/cancel', [AiSalesAgentRunController::class, 'cancel'])->name('runs.cancel');
    });

Route::prefix('ai/price-lists')
    ->name('api.ai.price-lists.')
    ->middleware([EnforceAiPriceListAuthorization::class, 'throttle:120,1'])
    ->group(function (): void {
        Route::get('/meta/entities', [AiPriceListImportController::class, 'entities'])->name('entities');
        Route::get('/meta/goods', [AiPriceListImportController::class, 'goods'])->name('goods');
        Route::get('/', [AiPriceListImportController::class, 'index'])->name('index');
        Route::get('/{priceListImport}', [AiPriceListImportController::class, 'show'])->name('show');
        Route::get('/{priceListImport}/download', [AiPriceListImportController::class, 'download'])->name('download');
        Route::patch('/{priceListImport}/supplier', [AiPriceListImportController::class, 'assignSupplier'])->name('supplier');
        Route::post('/{priceListImport}/classification', [AiPriceListImportController::class, 'classify'])->name('classification');
        Route::post('/{priceListImport}/retry', [AiPriceListImportController::class, 'retry'])->name('retry');
        Route::post('/{priceListImport}/cancel', [AiPriceListImportController::class, 'cancel'])->name('cancel');
        Route::get('/{priceListImport}/apply-preview', [AiPriceListImportController::class, 'applyPreview'])->name('apply-preview');
        Route::post('/{priceListImport}/apply', [AiPriceListImportController::class, 'apply'])->name('apply');
        Route::get('/{priceListImport}/items', [AiPriceListItemController::class, 'index'])->name('items.index');
        Route::patch('/{priceListImport}/items/{priceListItem}', [AiPriceListItemController::class, 'update'])->name('items.update');
        Route::post('/{priceListImport}/items/{priceListItem}/decision', [AiPriceListItemController::class, 'decide'])->name('items.decision');
        Route::post('/{priceListImport}/items/bulk-confirm-exact', [AiPriceListItemController::class, 'bulkConfirmExact'])->name('items.bulk-confirm-exact');
        Route::post('/{priceListImport}/items/bulk-defaults', [AiPriceListItemController::class, 'bulkDefaults'])->name('items.bulk-defaults');
    });

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

use App\Http\Controllers\API\MailboxController;
use App\Http\Controllers\API\MailMessageActionController;
use App\Http\Controllers\API\MailMessageController;
use App\Http\Controllers\API\MaxChatController;
use App\Http\Controllers\API\MaxSubscriptionController;
use App\Http\Controllers\API\MaxWebhookController;

Route::apiResource('mailboxes', MailboxController::class)
    ->only(['index', 'store', 'show', 'update', 'destroy']);
Route::get('mail-messages/folders', [MailMessageController::class, 'folders'])
    ->name('mail-messages.folders');
Route::post('mail-messages/send', [MailMessageActionController::class, 'send'])
    ->name('mail-messages.send');
Route::get('mail-messages', [MailMessageController::class, 'index'])
    ->name('mail-messages.index');

Route::get('mail-messages/{mailMessage}', [MailMessageController::class, 'show'])
    ->name('mail-messages.show');
Route::delete('mail-messages/{mailMessage}', [MailMessageController::class, 'destroy'])
    ->name('mail-messages.destroy');
Route::post('mail-messages/{mailMessage}/attachments/sync', [MailMessageActionController::class, 'syncAttachments'])
    ->name('mail-messages.attachments.sync');
Route::get('mail-messages/{mailMessage}/attachments/{index}/download', [MailMessageActionController::class, 'downloadAttachment'])
    ->whereNumber('index')
    ->name('mail-messages.attachments.download');
Route::get('mail-messages/{mailMessage}/attachment-folders', [MailMessageActionController::class, 'attachmentFolders'])
    ->name('mail-messages.attachment-folders.index');
Route::post('mail-messages/{mailMessage}/attachment-folders', [MailMessageActionController::class, 'storeAttachmentFolder'])
    ->name('mail-messages.attachment-folders.store');
Route::post('mail-messages/{mailMessage}/attachments/{index}/save', [MailMessageActionController::class, 'saveAttachment'])
    ->whereNumber('index')
    ->name('mail-messages.attachments.save');
Route::post('mail-messages/{mailMessage}/notes', [MailMessageActionController::class, 'storeNote'])
    ->name('mail-messages.notes.store');
Route::post('mail-messages/{mailMessage}/lead', [MailMessageActionController::class, 'createLead'])
    ->name('mail-messages.lead.store');

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

Route::prefix('gis')
    ->name('api.gis.')
    ->group(function () {
        Route::get('/entities', [GisEntityController::class, 'index'])->name('entities.index');
        Route::get('/entities/no-location', [GisEntityController::class, 'noLocation'])->name('entities.no-location');
        Route::get('/entities/{entityId}/location', [GisEntityController::class, 'showLocation'])
            ->whereNumber('entityId')
            ->name('entities.location.show');
        Route::put('/entities/{entityId}/location', [GisEntityController::class, 'updateLocation'])
            ->whereNumber('entityId')
            ->name('entities.location.update');
        Route::post('/entities/{entityId}/geocode', [GisEntityController::class, 'geocode'])
            ->whereNumber('entityId')
            ->name('entities.geocode');

        Route::post('/routes/preview', [GisRouteController::class, 'preview'])->name('routes.preview');
        Route::post('/routes/distance-matrix', [GisRouteController::class, 'distanceMatrix'])->name('routes.distance-matrix');
        Route::post('/routes/drafts', [GisRouteController::class, 'store'])->name('routes.drafts.store');
        Route::get('/routes/drafts/{draft}', [GisRouteController::class, 'show'])->name('routes.drafts.show');
        Route::delete('/routes/drafts/{draft}', [GisRouteController::class, 'destroy'])->name('routes.drafts.destroy');
    });

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
 *  M A X
 * __________________
 */
Route::post('/max/webhook', MaxWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('api.max.webhook');

Route::prefix('max')
    ->name('api.max.')
    ->middleware('throttle:90,1')
    ->group(function () {
        Route::get('/chats', [MaxChatController::class, 'index'])->name('chats.index');
        Route::post('/chats', [MaxChatController::class, 'store'])->name('chats.store');
        Route::get('/chats/{maxChat}', [MaxChatController::class, 'show'])->name('chats.show');
        Route::put('/chats/{maxChat}', [MaxChatController::class, 'update'])->name('chats.update');
        Route::delete('/chats/{maxChat}', [MaxChatController::class, 'destroy'])->name('chats.destroy');
        Route::get('/chats/{maxChat}/messages', [MaxChatController::class, 'messages'])->name('chats.messages');
        Route::post('/chats/{maxChat}/sync', [MaxChatController::class, 'sync'])->name('chats.sync');

        Route::post('/messages/send', [MaxChatController::class, 'send'])->name('messages.send');
        Route::put('/messages/{maxMessage}', [MaxChatController::class, 'updateMessage'])->name('messages.update');
        Route::delete('/messages/{maxMessage}', [MaxChatController::class, 'destroyMessage'])->name('messages.destroy');

        Route::get('/subscriptions', [MaxSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions', [MaxSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::delete('/subscriptions/{maxSubscription}', [MaxSubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    });
//  E N D  M A X

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

    Route::post('/manufactures', [UnitRelationController::class, 'attachManufacture'])->name('api.units.manufactures.attach');
    Route::delete('/manufactures/{product}', [UnitRelationController::class, 'detachManufacture'])->name('api.units.manufactures.detach');

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
Route::post('checks/{check}/commodities', [CheckCommodityController::class, 'store'])
    ->name('checks.commodities.store');
Route::patch('check-commodities/{checkCommodity}', [CheckCommodityController::class, 'update'])
    ->name('check-commodities.update');
Route::delete('check-commodities/{checkCommodity}', [CheckCommodityController::class, 'destroy'])
    ->name('check-commodities.destroy');
Route::post('checks/{check}/services', [CheckServiceController::class, 'store'])
    ->name('checks.services.store');
Route::patch('check-services/{checkService}', [CheckServiceController::class, 'update'])
    ->name('check-services.update');
Route::delete('check-services/{checkService}', [CheckServiceController::class, 'destroy'])
    ->name('check-services.destroy');
Route::apiResource('checks', CheckController::class);
Route::apiResource('components', ComponentController::class);
Route::apiResource('countries', CountryController::class);
Route::apiResource('currencies', CurrencyController::class);
Route::apiResource('entities-classification', EntitiesClassification::class);
Route::get('fields/{field}/board', [FieldBoardController::class, 'show'])->name('api.fields.board');
Route::post('fields/{field}/matches', [FieldBoardController::class, 'store'])->name('api.fields.matches.store');
Route::patch('fields/{field}/matches/{match}', [FieldBoardController::class, 'update'])->name('api.fields.matches.update');
Route::delete('fields/{field}/matches/{match}', [FieldBoardController::class, 'destroy'])->name('api.fields.matches.destroy');
Route::apiResource('fields', FieldController::class);
Route::apiResource('expense-articles', ExpenseArticleController::class)
    ->parameters(['expense-articles' => 'expenseArticle']);
Route::apiResource('fragrances', FragranceController::class);
Route::apiResource('industries', IndustryController::class);
// если нужно быстро получить units по industry:
Route::get('industries/{industry}/units', [IndustryController::class, 'units']);
Route::apiResource('genera', GenusController::class);
Route::apiResource('goodsales', GoodSaleController::class);
Route::prefix('home-banner-assets')
    ->name('api.home-banner-assets.')
    ->group(function () {
        Route::get('/', [HomeBannerAssetController::class, 'index'])->name('index');
        Route::post('/upload', [HomeBannerAssetController::class, 'upload'])->name('upload');
        Route::post('/folders', [HomeBannerAssetController::class, 'storeFolder'])->name('folders.store');
        Route::patch('/rename', [HomeBannerAssetController::class, 'rename'])->name('rename');
        Route::patch('/move', [HomeBannerAssetController::class, 'move'])->name('move');
        Route::delete('/', [HomeBannerAssetController::class, 'destroy'])->name('destroy');
    });
Route::apiResource('home-banners', HomeBannerController::class)
    ->parameters(['home-banners' => 'homeBanner']);
Route::apiResource('labels', LabelController::class);
Route::apiResource('measures', MeasureController::class);
Route::apiResource('messages', MessageController::class);
Route::apiResource('notes', NoteController::class);
Route::prefix('orders')
    ->name('orders.')
    ->group(function (): void {
        Route::get('/options', [OrderController::class, 'options'])
            ->name('options');
        Route::get('/', [OrderController::class, 'index'])
            ->name('index');
        Route::post('/', [OrderController::class, 'store'])
            ->name('store');
        Route::get('/{order}', [OrderController::class, 'show'])
            ->name('show');
        Route::match(['put', 'patch'], '/{order}', [OrderController::class, 'update'])
            ->name('update');
        Route::delete('/{order}', [OrderController::class, 'destroy'])
            ->name('destroy');
    });
Route::apiResource('plants', PlantController::class);
Route::apiResource('price-types', PriceTypeController::class);
Route::apiResource('projects', ProjectController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('taxi-shifts', TaxiShiftController::class)
    ->parameters(['taxi-shifts' => 'taxiShift']);
Route::apiResource('warehouses', WarehouseController::class);
Route::get('warehouse-stock', [StockMovementController::class, 'stock'])
    ->name('warehouse-stock.index');
Route::apiResource('stock-movements', StockMovementController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameters(['stock-movements' => 'stockMovement']);
Route::get('good-warehouse-stock', [GoodStockMovementController::class, 'stock'])
    ->name('good-warehouse-stock.index');
Route::apiResource('good-stock-movements', GoodStockMovementController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->parameters(['good-stock-movements' => 'goodStockMovement']);
Route::get('good-stock-alerts', [GoodStockAlertAdminController::class, 'index'])
    ->name('good-stock-alerts.index');
Route::delete('good-stock-alerts/{goodStockAlert}', [GoodStockAlertAdminController::class, 'destroy'])
    ->name('good-stock-alerts.destroy');
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

        Route::get('/direct/ai-autopilot', [YandexDirectAiAutopilotController::class, 'dashboard'])
            ->name('direct.ai-autopilot.dashboard');
        Route::post('/direct/ai-autopilot/run', [YandexDirectAiAutopilotController::class, 'run'])
            ->name('direct.ai-autopilot.run');
        Route::post('/direct/ai-autopilot/decisions/{decision}/approve', [YandexDirectAiAutopilotController::class, 'approve'])
            ->name('direct.ai-autopilot.decisions.approve');
        Route::post('/direct/ai-autopilot/decisions/{decision}/reject', [YandexDirectAiAutopilotController::class, 'reject'])
            ->name('direct.ai-autopilot.decisions.reject');

        Route::get('/direct/geo-regions', [YandexDirectGeoRegionController::class, 'index'])
            ->name('direct.geo-regions.index');
        Route::post('/direct/geo-regions/resolve', [YandexDirectGeoRegionController::class, 'show'])
            ->name('direct.geo-regions.resolve');
        Route::post('/direct/geo-regions/sync', [YandexDirectGeoRegionController::class, 'sync'])
            ->name('direct.geo-regions.sync');

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
        Route::get('/direct/logs/{log}', [YandexSyncLogController::class, 'show'])
            ->name('direct.logs.show');
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
Route::post('/avito/webhook', [AvitoController::class, 'receiveWebhook'])
    ->middleware('throttle:300,1')
    ->name('api.avito.webhook');

Route::prefix('avito')->name('api.avito.')->middleware('throttle:120,1')->group(function () {
    Route::get('/status', [AvitoController::class, 'status'])->name('status');
    Route::get('/capabilities', [AvitoController::class, 'capabilities'])->name('capabilities.index');
    Route::patch('/capabilities', [AvitoController::class, 'bulkUpdateCapabilities'])->name('capabilities.bulk-update');
    Route::get('/capabilities/{capability}', [AvitoController::class, 'capability'])->name('capabilities.show');
    Route::patch('/capabilities/{capability}', [AvitoController::class, 'updateCapability'])->name('capabilities.update');
    Route::post('/capabilities/{capability}/execute', [AvitoController::class, 'execute'])
        ->middleware('throttle:20,1')
        ->name('capabilities.execute');
    Route::post('/preflight', [AvitoController::class, 'preflight'])
        ->middleware('throttle:10,1')
        ->name('preflight');

    Route::get('/connections', [AvitoController::class, 'connections'])->name('connections.index');
    Route::post('/connections/{connection}/refresh', [AvitoController::class, 'refreshConnection'])->name('connections.refresh');
    Route::delete('/connections/{connection}', [AvitoController::class, 'destroyConnection'])->name('connections.destroy');

    Route::get('/workspace-settings', [AvitoWorkspaceSettingsController::class, 'show'])
        ->name('workspace-settings.show');
    Route::put('/workspace-settings', [AvitoWorkspaceSettingsController::class, 'update'])
        ->name('workspace-settings.update');

    Route::get('/calls', [AvitoController::class, 'calls'])->name('calls.index');
    Route::get('/calls/{call}', [AvitoController::class, 'call'])->name('calls.show');
    Route::get('/webhooks', [AvitoController::class, 'webhooks'])->name('webhooks.index');
    Route::get('/webhooks/{event}', [AvitoController::class, 'webhook'])->name('webhooks.show');

    Route::prefix('listings')->name('listings.')->group(function () {
        Route::get('/context', [AvitoListingController::class, 'context'])->name('context');
        Route::get('/', [AvitoListingController::class, 'index'])->name('index');
        Route::get('/goods', [AvitoListingGoodController::class, 'goods'])->name('goods');
        Route::post('/statistics', [AvitoListingController::class, 'statistics'])->name('statistics');
        Route::post('/statistics/items', [AvitoListingController::class, 'itemStatistics'])->name('statistics.items');
        Route::post('/spendings', [AvitoListingController::class, 'spendings'])->name('spendings');
        Route::post('/promotions', [AvitoListingController::class, 'promotions'])
            ->middleware('throttle:20,1')
            ->name('promotions');
        Route::get('/{item}/good-link', [AvitoListingGoodController::class, 'show'])
            ->whereNumber('item')
            ->name('good-link.show');
        Route::put('/{item}/good-link', [AvitoListingGoodController::class, 'store'])
            ->whereNumber('item')
            ->name('good-link.store');
        Route::delete('/{item}/good-link', [AvitoListingGoodController::class, 'destroy'])
            ->whereNumber('item')
            ->name('good-link.destroy');
        Route::post('/{item}/good-transfer/preview', [AvitoListingGoodController::class, 'preview'])
            ->whereNumber('item')
            ->middleware('throttle:30,1')
            ->name('good-transfer.preview');
        Route::post('/{item}/good-transfer/apply', [AvitoListingGoodController::class, 'apply'])
            ->whereNumber('item')
            ->middleware('throttle:20,1')
            ->name('good-transfer.apply');
        Route::get('/{item}/good-transfer/media/{media}', [AvitoListingGoodController::class, 'media'])
            ->whereNumber('item')
            ->whereNumber('media')
            ->middleware('throttle:60,1')
            ->name('good-transfer.media');
        Route::get('/{item}', [AvitoListingController::class, 'show'])->whereNumber('item')->name('show');
        Route::post('/{item}/action', [AvitoListingController::class, 'action'])
            ->whereNumber('item')
            ->middleware('throttle:20,1')
            ->name('action');
    });

    Route::prefix('publications')->name('publications.')->group(function () {
        Route::get('/categories', [AvitoPublicationController::class, 'categories'])
            ->name('categories.index');
        Route::get('/categories/{nodeSlug}/fields', [AvitoPublicationController::class, 'categoryFields'])
            ->where('nodeSlug', '[A-Za-z0-9_-]+')
            ->name('categories.fields');
        Route::get('/feed', [AvitoPublicationController::class, 'feed'])->name('feed.show');
        Route::put('/feed', [AvitoPublicationController::class, 'updateFeed'])->name('feed.update');
        Route::get('/feed/profile', [AvitoPublicationController::class, 'checkProfile'])
            ->name('feed.profile.show');
        Route::post('/feed/profile', [AvitoPublicationController::class, 'attachProfile'])
            ->name('feed.profile.store');
        Route::get('/feed/upload', [AvitoPublicationController::class, 'uploadStatus'])
            ->name('feed.upload.show');
        Route::post('/feed/upload', [AvitoPublicationController::class, 'upload'])
            ->name('feed.upload.store');
        Route::get('/', [AvitoPublicationController::class, 'index'])->name('index');
        Route::post('/', [AvitoPublicationController::class, 'store'])->name('store');
        Route::get('/{publication}', [AvitoPublicationController::class, 'show'])
            ->whereNumber('publication')
            ->name('show');
        Route::put('/{publication}', [AvitoPublicationController::class, 'update'])
            ->whereNumber('publication')
            ->name('update');
        Route::post('/{publication}/preview', [AvitoPublicationController::class, 'preview'])
            ->whereNumber('publication')
            ->name('preview');
        Route::post('/{publication}/approve', [AvitoPublicationController::class, 'approve'])
            ->whereNumber('publication')
            ->name('approve');
        Route::post('/{publication}/sync', [AvitoPublicationController::class, 'sync'])
            ->whereNumber('publication')
            ->name('sync');
        Route::post('/{publication}/archive', [AvitoPublicationController::class, 'archive'])
            ->whereNumber('publication')
            ->name('archive');
    });

    Route::prefix('messenger')->name('messenger.')->group(function () {
        Route::get('/auto-replies', [AvitoAutoReplyController::class, 'index'])->name('auto-replies.index');
        Route::patch('/auto-replies/settings', [AvitoAutoReplyController::class, 'updateSettings'])
            ->name('auto-replies.settings.update');
        Route::post('/auto-replies/rules', [AvitoAutoReplyController::class, 'store'])
            ->name('auto-replies.rules.store');
        Route::match(['put', 'patch'], '/auto-replies/rules/{rule}', [AvitoAutoReplyController::class, 'update'])
            ->name('auto-replies.rules.update');
        Route::delete('/auto-replies/rules/{rule}', [AvitoAutoReplyController::class, 'destroy'])
            ->name('auto-replies.rules.destroy');
        Route::post('/auto-replies/test', [AvitoAutoReplyController::class, 'testPhrase'])
            ->middleware('throttle:20,1')
            ->name('auto-replies.test');
        Route::post('/auto-replies/archive-analysis', [AvitoAutoReplyController::class, 'analyzeArchive'])
            ->middleware('throttle:5,1')
            ->name('auto-replies.archive-analysis');
        Route::get('/templates', [AvitoMessageTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [AvitoMessageTemplateController::class, 'store'])->name('templates.store');
        Route::match(['put', 'patch'], '/templates/{template}', [AvitoMessageTemplateController::class, 'update'])
            ->name('templates.update');
        Route::delete('/templates/{template}', [AvitoMessageTemplateController::class, 'destroy'])
            ->name('templates.destroy');
        Route::get('/crm/options', [AvitoCrmController::class, 'options'])->name('crm.options');
        Route::get('/crm/entities', [AvitoCrmController::class, 'entities'])->name('crm.entities');
        Route::get('/crm/cities', [AvitoCrmController::class, 'cities'])->name('crm.cities');
        Route::get('/crm/goods', [AvitoCrmController::class, 'goods'])->name('crm.goods');
        Route::patch('/crm/candidates/{candidate}', [AvitoCrmController::class, 'updateCandidate'])
            ->name('crm.candidates.update');
        Route::get('/overview', [AvitoMessengerController::class, 'overview'])->name('overview');
        Route::get('/chats', [AvitoMessengerController::class, 'chats'])->name('chats.index');
        Route::get('/chats/{chat}', [AvitoMessengerController::class, 'chat'])->name('chats.show');
        Route::get('/chats/{chat}/crm', [AvitoCrmController::class, 'show'])->name('chats.crm.show');
        Route::put('/chats/{chat}/crm/entity', [AvitoCrmController::class, 'linkEntity'])
            ->name('chats.crm.entity.link');
        Route::post('/chats/{chat}/crm/entity', [AvitoCrmController::class, 'createEntity'])
            ->name('chats.crm.entity.store');
        Route::delete('/chats/{chat}/crm/entity', [AvitoCrmController::class, 'unlinkEntity'])
            ->name('chats.crm.entity.destroy');
        Route::post('/chats/{chat}/crm/telephones', [AvitoCrmController::class, 'storeTelephone'])
            ->name('chats.crm.telephones.store');
        Route::post('/chats/{chat}/crm/buildings', [AvitoCrmController::class, 'storeBuilding'])
            ->name('chats.crm.buildings.store');
        Route::post('/chats/{chat}/crm/orders', [AvitoCrmController::class, 'storeOrder'])
            ->middleware('throttle:30,1')
            ->name('chats.crm.orders.store');
        Route::post('/chats/{chat}/crm/goods/{good}/send', [AvitoCrmController::class, 'sendGood'])
            ->middleware('throttle:20,1')
            ->name('chats.crm.goods.send');
        Route::post('/chats/{chat}/message-templates/{template}/preview', [AvitoMessageTemplateController::class, 'preview'])
            ->name('chats.templates.preview');
        Route::post('/chats/{chat}/message-templates/{template}/send', [AvitoMessageTemplateController::class, 'send'])
            ->middleware('throttle:30,1')
            ->name('chats.templates.send');
        Route::post('/sync', [AvitoMessengerController::class, 'queueSync'])
            ->middleware('throttle:10,1')
            ->name('sync.store');
        Route::get('/sync-runs/{run}', [AvitoMessengerController::class, 'syncRun'])->name('sync-runs.show');
        Route::post('/chats/{chat}/refresh', [AvitoMessengerController::class, 'refreshChat'])
            ->middleware('throttle:20,1')
            ->name('chats.refresh');
        Route::post('/chats/{chat}/read', [AvitoMessengerController::class, 'markRead'])->name('chats.read');
        Route::post('/chats/{chat}/blacklist', [AvitoMessengerController::class, 'blacklist'])->name('chats.blacklist');
        Route::post('/chats/{chat}/messages', [AvitoMessengerController::class, 'sendText'])
            ->middleware('throttle:30,1')
            ->name('messages.store');
        Route::post('/chats/{chat}/messages/image', [AvitoMessengerController::class, 'sendImage'])
            ->middleware('throttle:20,1')
            ->name('messages.image');
        Route::delete('/messages/{message}', [AvitoMessengerController::class, 'destroyMessage'])
            ->middleware('throttle:30,1')
            ->name('messages.destroy');
        Route::get('/subscriptions', [AvitoMessengerController::class, 'subscriptions'])->name('subscriptions.index');
        Route::post('/subscriptions', [AvitoMessengerController::class, 'subscribe'])->name('subscriptions.store');
        Route::delete('/subscriptions', [AvitoMessengerController::class, 'unsubscribe'])->name('subscriptions.destroy');
        Route::get('/attachments/{attachment}', [AvitoMessengerController::class, 'attachment'])
            ->name('attachments.show');
    });
});

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

Route::prefix('logistics')
    ->name('api.logistics.')
    ->middleware(\App\Http\Middleware\EnforceLogisticsAuthorization::class)
    ->scopeBindings()
    ->group(function () {
        Route::get('/dashboard', LogisticsDashboardController::class)->name('dashboard');
        Route::get('/map/config', [LogisticsMapConfigurationController::class, 'show'])
            ->name('map.config');
        Route::get('/map/style', [LogisticsMapConfigurationController::class, 'style'])
            ->name('map.style');
        Route::get('/map/features', LogisticsMapController::class)
            ->middleware('throttle:120,1')
            ->name('map.features');
        Route::get('/references/{type}', LogisticsReferenceController::class)
            ->whereIn('type', ['cities', 'entities', 'users'])
            ->name('references.index');
        Route::get('/expense-categories', [LogisticsExpenseCategoryController::class, 'index'])
            ->name('expense-categories.index');
        Route::get('/checks', LogisticsCheckLookupController::class)->name('checks.index');

        Route::get('/vehicles', [LogisticsVehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [LogisticsVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [LogisticsVehicleController::class, 'show'])->name('vehicles.show');
        Route::put('/vehicles/{vehicle}', [LogisticsVehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [LogisticsVehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::post('/vehicles/{vehicle}/restore', [LogisticsVehicleController::class, 'restore'])
            ->whereNumber('vehicle')->name('vehicles.restore');

        Route::get('/trips', [LogisticsTripController::class, 'index'])->name('trips.index');
        Route::post('/trips', [LogisticsTripController::class, 'store'])->name('trips.store');
        Route::get('/trips/{trip}', [LogisticsTripController::class, 'show'])->name('trips.show');
        Route::put('/trips/{trip}', [LogisticsTripController::class, 'update'])->name('trips.update');
        Route::delete('/trips/{trip}', [LogisticsTripController::class, 'destroy'])->name('trips.destroy');
        Route::post('/trips/{trip}/stops/{stop}/move', [LogisticsTripStopController::class, 'move'])
            ->name('trips.stops.move');

        Route::get('/trips/{trip}/expenses', [LogisticsTripExpenseController::class, 'index'])
            ->name('trips.expenses.index');
        Route::post('/trips/{trip}/expenses', [LogisticsTripExpenseController::class, 'store'])
            ->name('trips.expenses.store');
        Route::put('/trips/{trip}/expenses/{expense}', [LogisticsTripExpenseController::class, 'update'])
            ->name('trips.expenses.update');
        Route::delete('/trips/{trip}/expenses/{expense}', [LogisticsTripExpenseController::class, 'destroy'])
            ->name('trips.expenses.destroy');
        Route::get('/trips/{trip}/routes', [LogisticsTripRoutingController::class, 'index'])
            ->name('trips.routes.index');
        Route::get('/trips/{trip}/map', [LogisticsTripMapController::class, 'current'])
            ->name('trips.map');
        Route::get('/trips/{trip}/routes/{route}/map', [LogisticsTripMapController::class, 'version'])
            ->name('trips.routes.map');
        Route::post('/trips/{trip}/routes/calculate', [LogisticsTripRoutingController::class, 'store'])
            ->name('trips.routes.calculate');

        Route::get('/cities', [LogisticsCityController::class, 'index'])->name('cities.index');
        Route::put('/cities/{city}', [LogisticsCityController::class, 'update'])->name('cities.update');
        Route::get('/matrix', [LogisticsMatrixController::class, 'index'])->name('matrix.index');
        Route::get('/matrix/export', [LogisticsMatrixController::class, 'export'])->name('matrix.export');
        Route::post('/matrix/calculate', [LogisticsMatrixController::class, 'calculate'])->name('matrix.calculate');
        Route::put('/matrix/manual', [LogisticsMatrixController::class, 'manual'])->name('matrix.manual');
        Route::get('/matrix/{distance}/preview', LogisticsMatrixPreviewController::class)
            ->whereNumber('distance')
            ->middleware('throttle:60,1')
            ->name('matrix.preview');
        Route::get('/routing-runs', [LogisticsRoutingRunController::class, 'index'])->name('routing-runs.index');
        Route::get('/routing-runs/{run}', [LogisticsRoutingRunController::class, 'show'])->name('routing-runs.show');
        Route::get('/routing-status', LogisticsRoutingStatusController::class)->name('routing-status');
    });
