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

Route::get('/glycerol/', [\App\Http\Controllers\GlycerolController::class, 'index'])
    ->name('glycerol');

Route::get('/товары/', [\App\Http\Controllers\GoodController::class, 'index'])
    ->name('goods');

Route::get('/Ameise/', [\App\Http\Controllers\Verwalter::class, 'index'])
    ->name('verwalter');
Route::get('/Ameise/unit/{id}', [UnitController::class, 'show'])
    ->name('unit.show');
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

Route::apiResource('api/countries', \App\Http\Controllers\API\CountryController::class)
    ->name('index', 'api.countries');
Route::apiResource('api/labels', \App\Http\Controllers\API\LabelController::class)
    ->name('index', 'api.labels');


//                           P        O         S         T
Route::post('/api/unit/store', [\App\Http\Controllers\API\UnitController::class, 'store'])
    ->name('api.units.store');
Route::post('/api/uri/store', [\App\Http\Controllers\API\UriController::class, 'store'])
    ->name('api.uri.store');
Route::post('/api/product/store', [\App\Http\Controllers\API\ProductController::class, 'store'])
    ->name('api.product.store');
Route::post('/api/good/store', [\App\Http\Controllers\API\GoodController::class, 'store'])
    ->name('api.good.store');
