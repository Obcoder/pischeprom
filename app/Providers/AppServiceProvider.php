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
use App\Observers\GoodStockMovementObserver;
use App\Policies\BankAuditEventPolicy;
use App\Policies\BankConnectionPolicy;
use App\Policies\BankPaymentOrderDraftPolicy;
use App\Policies\BankTransactionPolicy;
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
