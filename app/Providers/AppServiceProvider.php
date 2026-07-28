<?php

namespace App\Providers;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Events\BankSyncFailed;
use App\Domain\Banking\Events\BankTransactionChanged;
use App\Domain\Banking\Events\ReceivablePaymentStatusChanged;
use App\Domain\Banking\Services\BankNotificationService;
use App\Domain\Banking\Services\BankProviderManager;
use App\Models\BankAuditEvent;
use App\Models\BankConnection;
use App\Models\BankPaymentOrderDraft;
use App\Models\BankTransaction;
use App\Models\GoodStockMovement;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripExpense;
use App\Models\Vehicle;
use App\Observers\GoodStockMovementObserver;
use App\Policies\BankAuditEventPolicy;
use App\Policies\BankConnectionPolicy;
use App\Policies\BankPaymentOrderDraftPolicy;
use App\Policies\BankTransactionPolicy;
use App\Policies\LogisticsCityDistancePolicy;
use App\Policies\LogisticsCityPolicy;
use App\Policies\LogisticsTripExpensePolicy;
use App\Policies\LogisticsTripPolicy;
use App\Policies\VehiclePolicy;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\Providers\FakeRoutingProvider;
use App\Services\Logistics\Routing\Providers\ValhallaRoutingProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(
            BankProviderInterface::class,
            fn ($app) => $app->make(BankProviderManager::class)->driver()
        );

        $this->app->singleton(RoutingProviderInterface::class, function ($app) {
            return match (config('logistics.routing_driver')) {
                'fake' => $app->make(FakeRoutingProvider::class),
                'valhalla' => $app->make(ValhallaRoutingProvider::class),
                default => throw new \LogicException('Unsupported logistics routing driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        GoodStockMovement::observe(GoodStockMovementObserver::class);

        RateLimiter::for('bank-oauth', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-sync', fn (Request $request) => Limit::perMinute(2)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-reconcile', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-drafts', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        Gate::before(function ($user, string $ability) {
            if (Str::startsWith($ability, 'logistics.')) {
                if (($user->status ?? 'active') === 'blocked') {
                    return false;
                }

                try {
                    if (method_exists($user, 'hasRole') && $user->hasRole('admin', 'crm')) {
                        return true;
                    }

                    return method_exists($user, 'hasPermissionTo')
                        && $user->hasPermissionTo($ability, 'crm');
                } catch (Throwable) {
                    return false;
                }
            }

            if (Str::startsWith($ability, 'bank.')) {
                if (($user->status ?? 'active') === 'blocked') {
                    return false;
                }

                try {
                    $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('admin', 'crm');

                    if ($ability === 'bank.manage_connection' && ! $isAdmin) {
                        return false;
                    }

                    if ($isAdmin) {
                        return true;
                    }

                    return method_exists($user, 'hasPermissionTo')
                        && $user->hasPermissionTo($ability, 'crm');
                } catch (Throwable) {
                    return false;
                }
            }

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

        Gate::policy(BankConnection::class, BankConnectionPolicy::class);
        Gate::policy(BankTransaction::class, BankTransactionPolicy::class);
        Gate::policy(BankPaymentOrderDraft::class, BankPaymentOrderDraftPolicy::class);
        Gate::policy(BankAuditEvent::class, BankAuditEventPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(LogisticsTrip::class, LogisticsTripPolicy::class);
        Gate::policy(LogisticsTripExpense::class, LogisticsTripExpensePolicy::class);
        Gate::policy(LogisticsCity::class, LogisticsCityPolicy::class);
        Gate::policy(LogisticsCityDistance::class, LogisticsCityDistancePolicy::class);

        Event::listen(
            ReceivablePaymentStatusChanged::class,
            [BankNotificationService::class, 'paymentStatusChanged']
        );
        Event::listen(BankSyncFailed::class, [BankNotificationService::class, 'syncFailed']);
        Event::listen(
            BankConnectionRequiresAttention::class,
            [BankNotificationService::class, 'connectionAttention']
        );
        Event::listen(BankTransactionChanged::class, [BankNotificationService::class, 'transactionChanged']);
    }
}
