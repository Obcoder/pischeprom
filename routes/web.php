<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Category;
use App\Models\City;
use App\Models\Good;
use App\Models\Product;
use App\Models\Region;
use App\Models\Unit;
use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\Verwalter;
use App\Http\Controllers\API\BuildingController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CityController;
use App\Http\Controllers\API\CheckController;
use App\Http\Controllers\API\EntityController;
use App\Http\Controllers\API\GenusController;
use App\Http\Controllers\API\GoodController;
use App\Http\Controllers\API\ManufacturerController;
use App\Http\Controllers\API\PriceController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UnitUriController;
use App\Http\Controllers\API\UriController;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

Route::get('/', [MainController::class, 'index'])
    ->name('home');

//   * * * * * * * * *   A M E I S E   * * * * * * * * *
Route::get('/Ameise/', [Verwalter::class, 'index'])
    ->name('verwalter');
//   * * * * * * * * * * * * * * * * * * * * * * * * * *

//     * * * * *   W O R K  B O A R D     * * * * *
Route::get('/Ameise/workboard', function () {
    return Inertia::render('Ameise/WorkBoard');
})->name('ameise.workboard');
//   A V I T O
Route::get('/Ameise/Avito', function () {
    return Inertia::render('Ameise/Avito');
})->name('Ameise/avito');
//   B O T A N Y
Route::get('/Ameise/Botany/', function (){
    return Inertia::render('Ameise/Botany');
})->name('Ameise.botany');
//   B R A N D S
Route::get('/Ameise/brands', function (){
    return Inertia::render('Ameise/Brands');
})->name('Ameise.brands');
//   C A T E G O R I E S
Route::get('/категория/{id}', function ($id) {
    return Inertia::render('Categories', ['category' => Category::findOrFail($id)]);
})->name('Categories');
//   C H E C K S
Route::get('Ameise/checks', function (){
    return Inertia::render('Ameise/Checks');
})->name('Ameise.checks');
//   C I T I E S
Route::get('/Ameise/Cities', function (){
    return Inertia::render('Ameise/Cities');
})->name('Ameise.cities');
Route::get('/Ameise/city/{id}', function ($id) {
    $data = [
        'city' => City::with('buildings')->with('entities')->findOrFail($id)
    ];
    return Inertia::render('Ameise/City', $data);
})->name('city.show');
//   C O M M O D I T I E S
Route::get('/Ameise/Commodities/', function (){
    return Inertia::render('Ameise/Commodities');
})->name('Ameise.commodities');
//   C O N T A C T S  C E N T R E
Route::get('/Ameise/ContactsCentre', function (){
    return Inertia::render('Ameise/ContactsCentre');
})->name('Ameise.contactsCentre');
Route::get('/Ameise/entities/', function (){
    return Inertia::render('Ameise/Entities');
})->name('Ameise.entities');
Route::get('/Ameise/FluxMonitor/', function (){
    return Inertia::render('Ameise/FluxMonitor');
})->name('Ameise.fluxmonitor');
//   G E O G R A P H Y
Route::get('/Ameise/Geography/', function (){
    return Inertia::render('Ameise/Geography');
})->name('Ameise.geography');
//   G O O D S
Route::get('/Ameise/Goods/', function (){
    return Inertia::render('Ameise/Goods');
})->name('Ameise.goods');
Route::get('/Ameise/goods/{id}/{slug?}', function ($id){
    $data = [
        'good' => Good::with('prices')->findOrFail($id),
    ];
    return Inertia::render('Ameise/Good', $data);
})->name('Ameise.good.show');
Route::get('/goods/published', [GoodController::class, 'indexPublished'])
    ->name('goods.published');
//   P R O D U C T S
Route::get('/Ameise/products/', function (){
    return Inertia::render('Ameise/Products');
})->name('Ameise.products');
Route::get('/Ameise/product/{id}', function ($id) {
    $product = Product::with('consumers.product')
        ->with('consumers.unit')
        ->with('consumers.measure')
        ->with('goods')
        ->findOrFail($id);
    return Inertia::render('Ameise/Product', ['product'=>$product]);
})->name('product.show');
//   R E G I O N
Route::get('/Ameise/region/{id}', function ($id){
    $data = [
        'region' => Region::with('cities')->findOrFail($id)
    ];
    return Inertia::render('Ameise/Region', $data);
})->name('Ameise.region');
//     S A L E S
Route::get('/Ameise/Sales/', function (){
    return Inertia::render('Ameise/Sales');
})->name('Ameise.sales');
//   S E A P R O M
Route::get('Seaprom', function (){
    return Inertia::render('Seaprom');
});
//     S E S A M E
Route::get('/кунжут/', function (){
    return Inertia::render('Sesame');
})->name('web.sesame');
//     U N I T S
Route::get('/Ameise/units/', function (){
    return Inertia::render('Ameise/Units');
})->name('Ameise.units');
Route::get('/Ameise/unit/{id}', function ($id){
    $data = [
        'unit' => Unit::with('entities.telephones')
            ->with('entities.sales')
            ->with('buildings')
            ->with('consumptions.product')
            ->with('consumptions.measure')
            ->with('manufactures')
            ->findOrFail($id),
    ];
    return Inertia::render('Ameise/Unit', $data);
})->name('web.unit.show');
//   Y A N D E X
Route::get('/Ameise/yandex', function (){
    return Inertia::render('Ameise/Yandex');
})->name('Ameise.yandex');


//                     A         P         I
//                          G         E         T
//
Route::apiResource('/api/building_units', \App\Http\Controllers\API\BuildingUnitController::class)
    ->name('store', 'api.building_unit.store');
Route::apiResource('/api/chats', \App\Http\Controllers\API\ChatController::class)
    ->name('index', 'api.chats');
Route::apiResource('/api/commodities', \App\Http\Controllers\API\CommodityController::class)
    ->name('index', 'api.commodities')
    ->name('store', 'api.commodity.store');
Route::apiResource('/api/components', \App\Http\Controllers\API\ComponentController::class)
    ->name('index', 'api.components')
    ->name('store', 'api.components.store');
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
Route::post('/api/checkcommodity/store', [\App\Http\Controllers\API\CheckCommodityController::class, 'store'])
    ->name('api.checkcommodity.store');
//    C I T Y
Route::post('/city/store', [CityController::class, 'store'])->name('web.city.store');
Route::post('/api/email/store', [\App\Http\Controllers\API\EmailController::class, 'store'])
    ->name('web.email.store');
Route::post('/api/emailgood/store', [\App\Http\Controllers\API\EmailUnitController::class, 'store'])
    ->name('emailgood.store');
//    C H E C K
Route::post('/api/check/store', [CheckController::class, 'store'])
    ->name('web.check.store');
//    E N T I T Y
Route::post('/entity/store', [EntityController::class, 'store'])
    ->name('web.entity.store');
//    G E N U S
Route::post('/api/genus/store', [GenusController::class, 'store'])
    ->name('web.genus.store');
Route::post('/api/labelunit/store', [\App\Http\Controllers\API\LabelUnitController::class, 'store'])
    ->name('web.labelunit.store');
//    G O O D  P O S T
Route::post('/api/good/store', [GoodController::class, 'store'])
    ->name('web.good.store');
//      M A N U F A C T U R E R
Route::post('/api/manufactirer/store', [ManufacturerController::class, 'store'])
    ->name('web.manufacturer.store');
//      P R I C E
Route::post('/api/price/store', [PriceController::class, 'store'])
    ->name('web.price.store');
//      P R O D U C T
Route::post('/api/product/store', [ProductController::class, 'store'])
    ->name('web.product.store');
//      U N I T
Route::post('/api/unit/store', [UnitController::class, 'store'])
    ->name('web.unit.store');
Route::post('/api/unituri/store', [UnitUriController::class, 'store'])
    ->name('web.unituri.store');
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
//Route::post('/api/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
//    ->name('api.mail');

Route::get('/send-email', function () {
    $data = [
        'title' => 'Test Email from Laravel',
        'message' => 'This is a test email sent via Elastic Email.'
    ];

    Mail::to('obcoder@gmail.com')->send(new TestEmail($data));

    return 'Email sent successfully!';
});

////////////      T E L E G R A M  B O T      /////////////////////
/// __________________________________________________________ ////
Route::get('/Ameise/TelegramBot/', function (){
    return Inertia::render('Ameise/TelegramBot');
})->name('ameise.telegrambot');
///E N D//////////////////////////////////////////////////////////


Route::patch('/genera/{genus}/toggle-agriculturable', [GenusController::class, 'toggleAgriculturable'])
    ->name('genera.toggleAgriculturable');

