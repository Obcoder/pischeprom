<?php

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
