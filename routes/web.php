<?php

use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\CheckCommodityController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\CommodityController;
use App\Http\Controllers\API\FragranceController;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\GoodSaleController;
use App\Http\Controllers\API\ManufacturerController;
use App\Http\Controllers\API\NoteController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\UnitController as ApiUnitController;
use App\Http\Controllers\API\UriController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\Verwalter;
use App\Http\Controllers\Web\CategoryController as WebCategoryController;
use App\Http\Controllers\Web\CustomerDashboardController;
use App\Http\Controllers\Web\CustomerProfileController;
use App\Http\Controllers\Web\EntityLookupController;
use App\Http\Controllers\Web\FieldController as WebFieldController;
use App\Http\Controllers\Web\GisPageController;
use App\Http\Controllers\Web\GoodController as WebGoodController;
use App\Http\Controllers\Web\LegalPageController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\ProductController as WebProductController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\UnitController;
use App\Http\Controllers\Web\UnitRelationSyncController;
use App\Http\Controllers\Web\UnitUriController as WebUnitUriController;
use App\Mail\TestEmail;
use App\Models\City;
use App\Models\Good;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| И Н Т Е Р Н Е Т - М А Г А З И Н
|--------------------------------------------------------------------------
*/

//     D A S H B O A R D

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::get('/dashboard', [CustomerDashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/dashboard/profile', [CustomerProfileController::class, 'edit'])
        ->name('customer.profile.edit');

    Route::post('/dashboard/profile', [CustomerProfileController::class, 'update'])
        ->name('customer.profile.update');
});

//  E N D  D A S H B O A R D

Route::get('/g', [WebGoodController::class, 'index'])
    ->name('public.goods.index');

Route::get('/g/{good}', [WebGoodController::class, 'show'])
    ->name('public.goods.show');

Route::get('/подборки/{field}', [WebFieldController::class, 'show'])
    ->name('public.fields.show');

Route::get('/товар/{good:slug}', function (\App\Models\Good $good) {
    return redirect()->route('public.goods.show', [
        'good' => $good->slug,
    ], 301);
})->name('public.goods.redirect');

Route::get('/location/cities', [LocationController::class, 'cities'])
    ->name('location.cities');

Route::post('/location/city', [LocationController::class, 'updateCity'])
    ->name('location.city.update');

Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])
    ->name('legal.privacy');

Route::get('/terms', [LegalPageController::class, 'terms'])
    ->name('legal.terms');

Route::get('/personal-data-consent', [LegalPageController::class, 'personalDataConsent'])
    ->name('legal.personal-data-consent');

/*
|--------------------------------------------------------------------------
| A M E I S E  (CRM / ERP)
|--------------------------------------------------------------------------
*/

//    H O M E
Route::get('/', [MainController::class, 'index'])
    ->name('home');
//   * * * * * * * * *   A M E I S E   * * * * * * * * *
Route::get('/Ameise/', [Verwalter::class, 'index'])
    ->name('Ameise');
//   * * * * * * * * * * * * * * * * * * * * * * * * * *

//     * * * * *   W O R K  B O A R D     * * * * *
Route::get('/Ameise/workboard', function () {
    return Inertia::render('Ameise/WorkBoard');
})->name('ameise.workboard');

// ⬇️⬇️⬇️
// ВСЕ остальные /Ameise/* маршруты
// просто ПЕРЕНОСЯТСЯ СЮДА БЕЗ ИЗМЕНЕНИЙ

//   B O T A N Y
Route::get('/Ameise/Botany/', function () {
    return Inertia::render('Ameise/Botany');
})->name('Ameise.botany');

//   C A T E G O R I E S

Route::get('/категория/{category}', [WebCategoryController::class, 'show'])
    ->name('category.show');

//   C H E C K S
Route::get('Ameise/checks', function () {
    return Inertia::render('Ameise/Checks');
})->name('Ameise.checks');
//

//   C I T I E S
Route::get('/Ameise/Cities', function () {
    return Inertia::render('Ameise/Cities');
})->name('Ameise.cities');
Route::get('/Ameise/city/{city}', function (City $city) {
    return Inertia::render('Ameise/City', [
        'cityId' => $city->id,
    ]);
})->name('city.show');
//

/*
|--------------------------------------------------------------------------
| C O M M O D I T I E S
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Web\CommodityController as WebCommodityController;

Route::get('/Ameise/Commodities/', function () {
    return Inertia::render('Ameise/Commodities');
})->name('Ameise.commodities');

Route::get('/Ameise/Commodities/{commodity}', [WebCommodityController::class, 'show'])
    ->name('Ameise.commodity.show');

//  E N D  C O M M O D I T I E S

//   C O N T A C T S  C E N T R E
Route::get('/Ameise/ContactsCentre', function () {
    return Inertia::render('Ameise/ContactsCentre');
})->name('Ameise.contactsCentre');

Route::get('/Ameise/Mail', function () {
    return Inertia::render('Ameise/Mail');
})->name('Ameise.mail');

//

//      E N T I T I E S
Route::get('/Ameise/entity', function () {
    return Inertia::render('Ameise/Entity', [
        'entityId' => null,
    ]);
})->name('Ameise.entity.create');

Route::get('/Ameise/entity/{entity}', function (\App\Models\Entity $entity) {
    return Inertia::render('Ameise/Entity', [
        'entityId' => $entity->id,
    ]);
})->name('Ameise.entity.show');

Route::prefix('web')->name('web.')->group(function () {
    Route::get('/entities', [EntityController::class, 'index'])
        ->name('entities.index');

    Route::get('/entities/lookup-by-inn', [EntityLookupController::class, 'lookupByInn'])
        ->middleware('throttle:30,1')
        ->name('entities.lookup-by-inn');

    Route::get('/entities/building-postcode', [EntityLookupController::class, 'buildingPostcode'])
        ->middleware('throttle:60,1')
        ->name('entities.building-postcode');

    Route::get('/entities/lookup', [EntityLookupController::class, 'lookup'])
        ->middleware('throttle:30,1')
        ->name('entities.lookup');
});
//  E N D  E N T I T I E S

//     F L U X  M O N I T O R
Route::get('/Ameise/FluxMonitor/', function () {
    return Inertia::render('Ameise/FluxMonitor');
})->name('Ameise.fluxmonitor');

Route::get('/Ameise/fields', function () {
    return Inertia::render('Ameise/FieldBoard');
})->name('Ameise.fields');

//   G E O G R A P H Y
Route::get('/Ameise/Geography/', function () {
    return Inertia::render('Ameise/Geography');
})->name('Ameise.geography');

Route::get('/gis/2gis', [GisPageController::class, 'twoGis'])
    ->name('gis.2gis');

Route::get('/gis/yandex', [GisPageController::class, 'yandex'])
    ->name('gis.yandex');

Route::get('/gis/entities/no-location', [GisPageController::class, 'noLocation'])
    ->name('gis.entities.no-location');

Route::get('/Ameise/home-banners', function () {
    return Inertia::render('Ameise/HomeBanners');
})->name('Ameise.home-banners');

/*
|--------------------------------------------------------------------------
| G O O D S
|--------------------------------------------------------------------------
*/
Route::get('/Ameise/Goods/', function () {
    return Inertia::render('Ameise/Goods');
})->name('Ameise.goods');

Route::get('/Ameise/goods/{id}/{slug?}', function ($id) {
    $data = [
        'good' => Good::findOrFail($id),
    ];

    return Inertia::render('Ameise/Good', $data);
})->name('Ameise.good.show');

Route::get('/goods/published', [GoodController::class, 'indexPublished'])
    ->name('goods.published');

Route::post('/api/good/store', [GoodController::class, 'store'])
    ->name('web.good.store');

//  E N D  G O O D

//     G R O S S B U C H
Route::get('/Ameise/grossbuch/', function () {
    return Inertia::render('Ameise/Grossbuch');
})->name('Ameise.großbuch');

Route::get('/Ameise/settings', function () {
    return Inertia::render('Ameise/Settings');
})->name('Ameise.settings');

//   P E R F U M E
Route::get('/Ameise/perfume/', function () {
    return Inertia::render('Ameise/Perfume');
})->name('Ameise.perfume');
//   P R O D U C T S
Route::get('/Ameise/products/', function () {
    return Inertia::render('Ameise/Products');
})->name('Ameise.products');

Route::get('/Ameise/product/{product}', function (Product $product) {
    return Inertia::render('Ameise/Product_02', [
        'id' => $product->id,
    ]);
})->name('product.show');

//   R E G I O N
Route::get('/Ameise/region/{id}', function ($id) {
    $data = [
        'region' => Region::with('cities')->findOrFail($id),
    ];

    return Inertia::render('Ameise/Region', $data);
})->name('Ameise.region');

//     P R O D U C T S

Route::get('/p/{product}', [WebProductController::class, 'show'])
    ->name('shop.products.show');

//  E N D  P R O D U C T S

//     P U R C H A S E
Route::prefix('purchases')
    ->name('web.purchases.')
    ->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseController::class, 'create'])->name('create');
        Route::get('/{purchase}/edit', [PurchaseController::class, 'edit'])->name('edit');
    });

//     S A L E S
Route::get('/Ameise/Sales/', function () {
    return Inertia::render('Ameise/Sales');
})->name('Ameise.sales');

/*
|--------------------------------------------------------------------------
| S E O
|--------------------------------------------------------------------------
*/
Route::get('/robots.txt', [SeoController::class, 'robots'])
    ->name('seo.robots');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])
    ->name('seo.sitemap');

Route::get('/yandex-feed.xml', [SeoController::class, 'yandexFeed'])
    ->name('seo.yandex-feed');

Route::get('/indexnow-key.txt', [SeoController::class, 'indexNowKey'])
    ->name('seo.indexnow-key');

//  E N D  S E O

//     U N I T S

Route::get('/Ameise/units/', function () {
    return Inertia::render('Ameise/Units');
})->name('Ameise.units');

Route::get('/Ameise/unit/{unit}', [UnitController::class, 'show'])
    ->name('web.unit.show');

Route::post('/api/unit/store', [ApiUnitController::class, 'store'])
    ->name('web.unit.store');

Route::post('/web/unit-uri', [WebUnitUriController::class, 'store'])->name('web.unituri.store');
Route::delete('/web/units/{unit}/uris/{uri}', [WebUnitUriController::class, 'destroy'])->name('web.unituri.destroy');

Route::post('/web/units/{unit}/labels/sync', [UnitRelationSyncController::class, 'syncLabels'])->name('web.units.labels.sync');
Route::post('/web/units/{unit}/fields/sync', [UnitRelationSyncController::class, 'syncFields'])->name('web.units.fields.sync');
Route::post('/web/units/{unit}/telephones/sync', [UnitRelationSyncController::class, 'syncTelephones'])->name('web.units.telephones.sync');
Route::post('/web/units/{unit}/cities/sync', [UnitRelationSyncController::class, 'syncCities'])->name('web.units.cities.sync');

//   Y A N D E X
Route::get('/Ameise/yandex', function () {
    return Inertia::render('Ameise/Yandex');
})->name('Ameise.yandex');

Route::get('/Ameise/marketing/yandex-direct', function () {
    return Inertia::render('Ameise/Marketing/YandexDirect');
})->name('Ameise.marketing.yandex-direct');

Route::get('/api/marketing/yandex/oauth/redirect', [\App\Http\Controllers\API\Marketing\YandexOAuthController::class, 'redirect'])
    ->name('api.marketing.yandex.oauth.redirect');

Route::get('/api/marketing/yandex/oauth/verification-code', [\App\Http\Controllers\API\Marketing\YandexOAuthController::class, 'verificationCode'])
    ->name('api.marketing.yandex.oauth.verification-code');

Route::get('/api/marketing/yandex/oauth/callback', [\App\Http\Controllers\API\Marketing\YandexOAuthController::class, 'callback'])
    ->name('api.marketing.yandex.oauth.callback');

//   A V I T O
Route::get('/Ameise/Avito', function () {
    return Inertia::render('Ameise/Avito');
})->name('Ameise/avito');

//   S E A P R O M
Route::get('Seaprom', function () {
    return Inertia::render('Seaprom');
});
//     S E S A M E
Route::get('/кунжут/', function () {
    return Inertia::render('Sesame');
})->name('web.sesame');

//                     A         P         I
//                          G         E         T
//
Route::apiResource('/api/building_units', \App\Http\Controllers\API\BuildingUnitController::class)
    ->name('store', 'api.building_unit.store');
Route::apiResource('/api/chats', \App\Http\Controllers\API\ChatController::class)
    ->name('index', 'api.chats');
Route::apiResource('/api/consumptions', \App\Http\Controllers\API\ConsumptionController::class)
    ->name('index', 'api.consumptions')
    ->name('store', 'api.consumption.store');
Route::apiResource('/api/entitiesclassifications/', \App\Http\Controllers\API\EntitiesClassification::class)
    ->name('index', 'api.entitiesclassifications');
Route::apiResource('/api/entity_unit', \App\Http\Controllers\API\EntityUnitController::class)
    ->name('store', 'api.entity_unit.store');
Route::get('/api/manufacturers/', [\App\Http\Controllers\API\ManufacturerController::class, 'index'])
    ->name('api.manufacturers');

//                           P        O         S         T
//    B U I L D I N G
Route::post('/api/building/store', [BuildingController::class, 'store'])
    ->name('web.building.store');
//    C I T Y
Route::post('/city/store', [CityController::class, 'store'])->name('web.city.store');
Route::post('/api/email/store', [\App\Http\Controllers\API\EmailController::class, 'store'])
    ->name('web.email.store');
Route::post('/api/emailgood/store', [\App\Http\Controllers\API\EmailUnitController::class, 'store'])
    ->name('emailgood.store');
//    C H E C K
Route::post('/api/check/store', [CheckController::class, 'store'])
    ->name('web.check.store');
//     C H E C K <-> C O M M O D I T Y
Route::post('/web/checkcommodity/store/', [CheckCommodityController::class, 'store'])
    ->name('web.checkcommodity.store');
//     C O M M O D I T Y
Route::post('/web/commodity/store', [CommodityController::class, 'store'])
    ->name('web.commodity.store');

//      F R A G R A N C E
Route::post('/web/fragrance/store', [FragranceController::class, 'store'])
    ->name('web.fragrance.store');

//    G E N U S
Route::post('/api/genus/store', [GenusController::class, 'store'])
    ->name('web.genus.store');
Route::patch('/genera/{genus}/toggle-agriculturable', [GenusController::class, 'toggleAgriculturable'])
    ->name('genera.toggleAgriculturable');

//    G O O D - S A L E
Route::post('/web/goodsale/store', [GoodSaleController::class, 'store'])
    ->name('web.goodsale.store');

//   L A B E L S

Route::post('/api/labelunit/store', [\App\Http\Controllers\API\LabelUnitController::class, 'store'])
    ->name('web.labelunit.store');

//      M A N U F A C T U R E R
Route::post('/api/manufactirer/store', [ManufacturerController::class, 'store'])
    ->name('web.manufacturer.store');
//      N O T E
Route::post('/web/note/store', [NoteController::class, 'store'])
    ->name('web.note.store');
//      P R O D U C T
Route::post('/api/product/store', [ProductController::class, 'store'])
    ->name('web.product.store');
//      Q U O T A T I O N
Route::post('/web/quotation/store', [QuotationController::class, 'store'])
    ->name('web.quotation.store');
//      S A L E
Route::post('/web/sale/store', [SaleController::class, 'store'])
    ->name('web.sale.store');

//    U R I
Route::post('/api/uri/store', [UriController::class, 'store'])
    ->name('web.uri.store');

/*
|--------------------------------------------------------------------------
|                       E M A I L S  J O B S
|--------------------------------------------------------------------------
|
|
|
 */
// Route::post('/api/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
//    ->name('api.mail');

Route::get('/send-email', function () {
    $data = [
        'title' => 'Test Email from Laravel',
        'message' => 'This is a test email sent via Elastic Email.',
    ];

    Mail::to('obcoder@gmail.com')->send(new TestEmail($data));

    return 'Email sent successfully!';
});

Route::get('/email/open/{token}', [EmailTrackingController::class, 'open'])
    ->name('email.open');

Route::get('/email/click/{token}', [EmailTrackingController::class, 'click'])
    ->name('email.click');

Route::get('/webhooks/unisender-go', [\App\Http\Controllers\Public\UnisenderWebhookController::class, 'verify'])
    ->name('webhooks.unisender-go.verify');
Route::post('/webhooks/unisender-go', [\App\Http\Controllers\Public\UnisenderWebhookController::class, 'handle'])
    ->name('webhooks.unisender-go.handle');
Route::get('/mailings/unsubscribe/{token}', [\App\Http\Controllers\Public\MailingUnsubscribeController::class, 'show'])
    ->name('mailings.unsubscribe.show');
Route::post('/mailings/unsubscribe/{token}', [\App\Http\Controllers\Public\MailingUnsubscribeController::class, 'unsubscribe'])
    ->name('mailings.unsubscribe.submit');

Route::prefix('Ameise/commercial-offers')->name('admin.commercial-offers.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'index'])->name('index');

    Route::get('/campaigns', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'campaigns'])->name('campaigns.index');
    Route::post('/campaigns', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'createCampaign'])->name('campaigns.store');
    Route::get('/campaigns/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'showCampaign'])->name('campaigns.show');
    Route::put('/campaigns/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'updateCampaign'])->name('campaigns.update');
    Route::post('/campaigns/{id}/preview', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'previewCampaign'])->name('campaigns.preview');
    Route::post('/campaigns/{id}/send-test', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'sendTestCampaign'])->name('campaigns.send-test');
    Route::post('/campaigns/{id}/approve', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'approveCampaign'])->name('campaigns.approve');
    Route::post('/campaigns/{id}/schedule', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'scheduleCampaign'])->name('campaigns.schedule');
    Route::post('/campaigns/{id}/start', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'startCampaign'])->name('campaigns.start');
    Route::post('/campaigns/{id}/pause', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'pauseCampaign'])->name('campaigns.pause');
    Route::post('/campaigns/{id}/resume', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'resumeCampaign'])->name('campaigns.resume');
    Route::post('/campaigns/{id}/cancel', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'cancelCampaign'])->name('campaigns.cancel');
    Route::post('/campaigns/{id}/duplicate', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'duplicateCampaign'])->name('campaigns.duplicate');
    Route::get('/campaigns/{id}/recipients', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'campaignRecipients'])->name('campaigns.recipients.index');
    Route::post('/campaigns/{id}/recipients', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'saveCampaignRecipients'])->name('campaigns.recipients.store');
    Route::delete('/campaigns/{id}/recipients/{recipientId}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'removeCampaignRecipient'])->name('campaigns.recipients.destroy');
    Route::get('/campaigns/{id}/recipient-picker/emails', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'recipientPickerEmails'])->name('campaigns.recipient-picker.emails');
    Route::get('/campaigns/{id}/recipient-picker/units', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'recipientPickerUnits'])->name('campaigns.recipient-picker.units');

    Route::get('/contacts', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'contacts'])->name('contacts.index');
    Route::get('/source-emails', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'sourceEmails'])->name('source-emails.index');
    Route::post('/contacts', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'createContact'])->name('contacts.store');
    Route::post('/contacts/import-existing-emails', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'importExistingEmails'])->name('contacts.import-existing-emails');
    Route::put('/contacts/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'updateContact'])->name('contacts.update');
    Route::post('/contacts/import', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'importContacts'])->name('contacts.import');
    Route::post('/contacts/bulk-update', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'bulkUpdateContacts'])->name('contacts.bulk-update');

    Route::get('/sets', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'sets'])->name('sets.index');
    Route::post('/sets', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'createSet'])->name('sets.store');
    Route::put('/sets/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'updateSet'])->name('sets.update');
    Route::post('/sets/{id}/members', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'addSetMembers'])->name('sets.members.store');
    Route::delete('/sets/{id}/members/{contactId}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'removeSetMember'])->name('sets.members.destroy');
    Route::post('/sets/{id}/duplicate', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'duplicateSet'])->name('sets.duplicate');

    Route::get('/templates', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'templates'])->name('templates.index');
    Route::post('/templates', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'createTemplate'])->name('templates.store');
    Route::put('/templates/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'updateTemplate'])->name('templates.update');
    Route::post('/templates/{id}/duplicate', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'duplicateTemplate'])->name('templates.duplicate');
    Route::post('/templates/{id}/sync-unisender', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'syncTemplate'])->name('templates.sync-unisender');
    Route::post('/templates/{id}/send-test', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'sendTestTemplate'])->name('templates.send-test');

    Route::post('/images', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'uploadImage'])->name('images.store');

    Route::get('/price-types', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'priceTypes'])->name('price-types.index');
    Route::get('/products/search', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'productSearch'])->name('products.search');
    Route::post('/campaigns/{id}/offer-items/add-filtered', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'addFilteredOfferItems'])->name('offer-items.add-filtered');
    Route::post('/campaigns/{id}/offer-items', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'addOfferItem'])->name('offer-items.store');
    Route::put('/offer-items/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'updateOfferItem'])->name('offer-items.update');
    Route::delete('/offer-items/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'deleteOfferItem'])->name('offer-items.destroy');
    Route::post('/campaigns/{id}/offer-items/reorder', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'reorderOfferItems'])->name('offer-items.reorder');

    Route::get('/events', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'events'])->name('events.index');
    Route::get('/suppression', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'suppression'])->name('suppression.index');
    Route::post('/suppression', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'addSuppression'])->name('suppression.store');
    Route::delete('/suppression/{id}', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'deleteSuppression'])->name('suppression.destroy');

    Route::post('/settings/test-api', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'testApi'])->name('settings.test-api');
    Route::post('/settings/set-webhook', [\App\Http\Controllers\Admin\CommercialOffersController::class, 'setWebhook'])->name('settings.set-webhook');
});

/*|
|-------------------------------------------------------------------------- */

// //////////      T E L E G R A M  B O T      /////////////////////
// / __________________________________________________________ ////
Route::get('/Ameise/TelegramBot/', function () {
    return Inertia::render('Ameise/TelegramBot');
})->name('ameise.telegrambot');
// /E N D//////////////////////////////////////////////////////////
