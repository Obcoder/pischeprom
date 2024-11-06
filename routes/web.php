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
Route::get('/api/manufacturers/', [ManufacturerController::class, 'index'])
    ->name('api.manufacturers');
Route::get('/api/products/', [\App\Http\Controllers\API\ProductController::class, 'index'])
    ->name('api.products');
