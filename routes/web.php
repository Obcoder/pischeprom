<?php

use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\ManufacturerController;
use App\Models\City;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\TelegramController;

Route::get('/', [\App\Http\Controllers\MainController::class, 'index'])
    ->name('home');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

Route::get('/товары/', [\App\Http\Controllers\GoodController::class, 'index'])
    ->name('goods');
Route::get('/Ameise/', [\App\Http\Controllers\Verwalter::class, 'index'])
    ->name('verwalter');
Route::get('/Ameise/Botany/', function (){
    return Inertia::render('Ameise/Botany');
})->name('Ameise.botany');
Route::get('/Ameise/FluxMonitor/', function (){
    return Inertia::render('Ameise/FluxMonitor');
})->name('ameise.fluxmonitor');
////////////      T E L E G R A M  B O T      /////////////////////
/// __________________________________________________________ ////
Route::get('/Ameise/TelegramBot/', function (){
    return Inertia::render('Ameise/TelegramBot');
})->name('ameise.telegrambot');
///E N D//////////////////////////////////////////////////////////

//     U N I T S
Route::get('/Ameise/units/', function (){
    return Inertia::render('Ameise/Units');
})->name('Ameise.units');
Route::get('/Ameise/unit/{id}', function ($id){
    $data = [
        'unit' => Unit::with('entities.telephones')
            ->with('stages')
            ->with('buildings')
            ->with('consumptions.product')
            ->with('consumptions.measure')
            ->findOrFail($id),
    ];
    return Inertia::render('Unit', $data);
})->name('unit.show');

// E N T I T I E S
Route::get('/Ameise/entities/', function (){
    return Inertia::render('Ameise/Entities');
})->name('Ameise.entities');

Route::get('/Ameise/check/{id}', [\App\Http\Controllers\CheckController::class, 'show'])
    ->name('check.show');
Route::get('Seaprom', function (){
    return Inertia::render('Seaprom');
});
Route::get('/Ameise/products/', function (){
    return Inertia::render('Ameise/Products');
})->name('Ameise.products');
Route::get('/Ameise/product/{id}', function ($id) {
    $product = Product::with('consumers.product')
        ->with('consumers.unit')
        ->with('consumers.measure')
        ->findOrFail($id);
    return Inertia::render('Ameise/Product', ['product'=>$product]);
})->name('product.show');
Route::get('/Ameise/city/{id}', function ($id) {
    $city = City::with('buildings')->findOrFail($id);
    return Inertia::render('Ameise/City', ['city'=>$city]);
})->name('city.show');
Route::get('/Ameise/Geography/', function (){
    return Inertia::render('Ameise/Geography');
})->name('Ameise.geography');


//                     A         P         I
//                          G         E         T
//
Route::apiResource('/api/buildings', \App\Http\Controllers\API\BuildingController::class)
    ->name('index', 'api.buildings')
    ->name('store', 'api.building.store');
Route::apiResource('/api/building_units', \App\Http\Controllers\API\BuildingUnitController::class)
    ->name('store', 'api.building_unit.store');
Route::apiResource('/api/catalogs', \App\Http\Controllers\API\CatalogController::class)
    ->name('index', 'api.catalogs');
Route::apiResource('/api/chats', \App\Http\Controllers\API\ChatController::class)
    ->name('index', 'api.chats');
Route::apiResource('/api/checks', \App\Http\Controllers\API\CheckController::class)
    ->name('index', 'api.checks')
    ->name('store', 'api.checks.store');
Route::apiResource('/api/cities', \App\Http\Controllers\API\CityController::class)
    ->name('index', 'api.cities')
    ->name('store', 'api.city.store');
Route::apiResource('/api/commodities', \App\Http\Controllers\API\CommodityController::class)
    ->name('index', 'api.commodities');
Route::apiResource('/api/components', \App\Http\Controllers\API\ComponentController::class)
    ->name('index', 'api.components')
    ->name('store', 'api.components.store');
Route::apiResource('/api/consumptions', \App\Http\Controllers\API\ConsumptionController::class)
    ->name('index', 'api.consumptions')
    ->name('store', 'api.consumption.store');
Route::apiResource('/api/countries/', \App\Http\Controllers\API\CountryController::class)
    ->name('index', 'api.countries');
Route::apiResource('/api/entities/', \App\Http\Controllers\API\EntityController::class)
    ->name('index', 'api.entities')
    ->name('store', 'api.entity.store');
Route::apiResource('/api/entitiesclassifications/', \App\Http\Controllers\API\EntitiesClassification::class)
    ->name('index', 'api.entitiesclassifications');
Route::apiResource('/api/entity_unit', \App\Http\Controllers\API\EntityUnitController::class)
    ->name('store', 'api.entity_unit.store');
Route::apiResource('/api/genera', \App\Http\Controllers\API\GenusController::class)
    ->name('index', 'api.genera');
Route::apiResource('/api/labels', \App\Http\Controllers\API\LabelController::class)
    ->name('index', 'api.labels');
Route::apiResource('/api/measures/', \App\Http\Controllers\API\MeasureController::class)
    ->name('index', 'api.measures');
Route::apiResource('/api/messages', \App\Http\Controllers\API\MessageController::class)
    ->name('index', 'api.messages')
    ->name('store', 'api.message.store');
Route::apiResource('/api/products', \App\Http\Controllers\API\ProductController::class)
    ->name('index', 'api.products')
    ->name('store', 'api.product.store');
Route::apiResource('/api/regions', \App\Http\Controllers\API\RegionController::class)
    ->name('index', 'api.regions');
Route::apiResource('/api/telephones', \App\Http\Controllers\API\TelephoneController::class)
    ->name('index', 'api.telephones')
    ->name('store', 'api.telephone.store');
Route::apiResource('/api/units/', App\Http\Controllers\API\UnitController::class)
    ->name('index', 'api.units');

Route::get('/api/manufacturers/', [\App\Http\Controllers\API\ManufacturerController::class, 'index'])
    ->name('api.manufacturers');
Route::get('/api/uris/', [\App\Http\Controllers\API\UriController::class, 'index'])
    ->name('api.uris');
Route::get('/api/categories/', [\App\Http\Controllers\API\CategoryController::class, 'index'])
    ->name('api.categories');


//                           P        O         S         T
Route::post('/api/unit/store', [\App\Http\Controllers\API\UnitController::class, 'store'])
    ->name('api.units.store');
Route::post('/api/uri/store', [\App\Http\Controllers\API\UriController::class, 'store'])
    ->name('api.uri.store');
Route::post('/api/good/store', [\App\Http\Controllers\API\GoodController::class, 'store'])
    ->name('api.good.store');
Route::post('/api/checkcommodity/store', [\App\Http\Controllers\API\CheckCommodityController::class, 'store'])
    ->name('api.checkcommodity.store');

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


