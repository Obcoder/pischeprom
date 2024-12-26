<?php

use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\ManufacturerController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
Route::get('/Ameise/unit/{id}', [UnitController::class, 'show'])
    ->name('unit.show');
Route::get('/Ameise/check/{id}', [\App\Http\Controllers\CheckController::class, 'show'])
    ->name('check.show');
Route::get('Seaprom', function (){
    return Inertia::render('Seaprom');
});

//                     A         P         I
//                          G         E         T
//
Route::get('/api/units/', [\App\Http\Controllers\API\UnitController::class, 'index'])
    ->name('api.units');
Route::get('/api/manufacturers/', [\App\Http\Controllers\API\ManufacturerController::class, 'index'])
    ->name('api.manufacturers');
Route::get('/api/products/', [\App\Http\Controllers\API\ProductController::class, 'index'])
    ->name('api.products');
Route::get('/api/uris/', [\App\Http\Controllers\API\UriController::class, 'index'])
    ->name('api.uris');
Route::get('/api/categories/', [\App\Http\Controllers\API\CategoryController::class, 'index'])
    ->name('api.categories');

Route::apiResource('/api/countries/', \App\Http\Controllers\API\CountryController::class)
    ->name('index', 'api.countries');
Route::apiResource('api/labels', \App\Http\Controllers\API\LabelController::class)
    ->name('index', 'api.labels');
Route::apiResource('api/entities', \App\Http\Controllers\API\EntityController::class)
    ->name('index', 'api.entities');
Route::apiResource('api/checks', \App\Http\Controllers\API\CheckController::class)
    ->name('index', 'api.checks')
    ->name('store', 'api.checks.store');
Route::apiResource('api/components', \App\Http\Controllers\API\ComponentController::class)
    ->name('index', 'api.components')
    ->name('store', 'api.components.store');


//                           P        O         S         T
Route::post('/api/unit/store', [\App\Http\Controllers\API\UnitController::class, 'store'])
    ->name('api.units.store');
Route::post('/api/uri/store', [\App\Http\Controllers\API\UriController::class, 'store'])
    ->name('api.uri.store');
Route::post('/api/product/store', [\App\Http\Controllers\API\ProductController::class, 'store'])
    ->name('api.product.store');
Route::post('/api/good/store', [\App\Http\Controllers\API\GoodController::class, 'store'])
    ->name('api.good.store');

/*
|--------------------------------------------------------------------------
|                       E M A I L S  J O B S
|--------------------------------------------------------------------------
|
|
|
 */
Route::post('/api/mail', [\App\Http\Controllers\MailController::class, 'sendMail'])
    ->name('api.mail');
Route::get('/send-email', function () {
    $data = [
        'title' => 'Test Email from Laravel',
        'message' => 'This is a test email sent via Elastic Email.'
    ];

    Mail::to('obcoder@gmail.com')->send(new TestEmail($data));

    return 'Email sent successfully!';
});
