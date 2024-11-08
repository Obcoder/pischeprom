<?php

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

Route::get('/verwalter/', [\App\Http\Controllers\Verwalter::class, 'index'])
    ->name('verwalter');


//                     A         P         I
//                          G         E         T
//
Route::get('/api/manufacturers/', [\App\Http\Controllers\API\ManufacturerController::class, 'index'])
    ->name('api.manufacturers');
Route::get('/api/products/', [\App\Http\Controllers\API\ProductController::class, 'index'])
    ->name('api.products');
Route::get('/api/units/', [\App\Http\Controllers\API\UnitController::class, 'index'])
    ->name('api.units');
Route::get('/api/uris/', [\App\Http\Controllers\API\UriController::class, 'index'])
    ->name('api.uris');


//                           P        O         S         T
Route::post('/api/unit/store', [\App\Http\Controllers\API\UnitController::class, 'store'])
    ->name('api.units.store');
