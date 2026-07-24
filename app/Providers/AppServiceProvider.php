<?php

namespace App\Providers;

use App\Models\GoodStockMovement;
use App\Observers\GoodStockMovementObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        GoodStockMovement::observe(GoodStockMovementObserver::class);

        Gate::before(function ($user, string $ability) {
            if (! Str::startsWith($ability, 'sales_mailings.')) {
                return null;
            }

            if (! method_exists($user, 'hasPermissionTo')) {
                return true;
            }

            try {
                return $user->hasPermissionTo($ability, 'crm') ?: null;
            } catch (Throwable) {
                return true;
            }
        });
    }
}
