<?php

namespace App\Providers;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\Contracts\StructuredTextModelProviderInterface;
use App\Domain\AiPriceLists\Providers\FakeOcrProvider;
use App\Domain\AiPriceLists\Providers\FakeStructuredTextModelProvider;
use App\Domain\AiPriceLists\Providers\YandexAiStudioProvider;
use App\Domain\AiPriceLists\Providers\YandexVisionOcrProvider;
use App\Domain\AiPriceLists\Services\ClamAvFileScanner;
use App\Domain\AiPriceLists\Services\NullFileScanner;
use App\Domain\AiSales\Contracts\EntityCreateLinkGuard;
use App\Domain\AiSales\Providers\AiProviderRegistry;
use App\Domain\AiSales\Services\DeterministicEntityCreateLinkGuard;
use App\Domain\AiSales\Tools\AiToolRegistry;
use App\Domain\AiSales\Workflows\AiWorkflowRegistry;
use App\Domain\Avito\Catalog\AvitoApiCatalog;
use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Events\BankSyncFailed;
use App\Domain\Banking\Events\BankTransactionChanged;
use App\Domain\Banking\Events\ReceivablePaymentStatusChanged;
use App\Domain\Banking\Services\BankNotificationService;
use App\Domain\Banking\Services\BankProviderManager;
use App\Infrastructure\AiSales\Providers\FakeExternalSanitizedAiProvider;
use App\Infrastructure\AiSales\Providers\FakeLocalRuAiProvider;
use App\Infrastructure\AiSales\Providers\TimewebExternalSanitizedProvider;
use App\Infrastructure\AiSales\Providers\TimewebLocalRuProvider;
use App\Models\AiAgentDefinition;
use App\Models\AiAgentRun;
use App\Models\BankAuditEvent;
use App\Models\BankConnection;
use App\Models\BankPaymentOrderDraft;
use App\Models\BankTransaction;
use App\Models\EntityCandidateProposal;
use App\Models\GoodStockMovement;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripExpense;
use App\Models\MailMessageAttachment;
use App\Models\PriceListImport;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitObservation;
use App\Models\Vehicle;
use App\Observers\GoodStockMovementObserver;
use App\Observers\MailMessageAttachmentObserver;
use App\Policies\AiSales\AiAgentDefinitionPolicy;
use App\Policies\AiSales\AiAgentRunPolicy;
use App\Policies\AiSales\EntityCandidateProposalPolicy;
use App\Policies\AiSales\UnitBusinessContextPolicy;
use App\Policies\AiSales\UnitObservationPolicy;
use App\Policies\AiSales\UnitPolicy;
use App\Policies\BankAuditEventPolicy;
use App\Policies\BankConnectionPolicy;
use App\Policies\BankPaymentOrderDraftPolicy;
use App\Policies\BankTransactionPolicy;
use App\Policies\LogisticsCityDistancePolicy;
use App\Policies\LogisticsCityPolicy;
use App\Policies\LogisticsTripExpensePolicy;
use App\Policies\LogisticsTripPolicy;
use App\Policies\PriceListImportPolicy;
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
        $this->app->singleton(AvitoApiCatalog::class);
        $this->app->bind(EntityCreateLinkGuard::class, DeterministicEntityCreateLinkGuard::class);
        $this->app->singleton(AiToolRegistry::class);
        $this->app->singleton(AiWorkflowRegistry::class);
        $this->app->singleton(AiProviderRegistry::class, function ($app): AiProviderRegistry {
            $registry = new AiProviderRegistry;

            if (config('ai-sales.transport_mode') === 'timeweb_synthetic_only') {
                $registry->register($app->make(TimewebLocalRuProvider::class));
                $registry->register($app->make(TimewebExternalSanitizedProvider::class));
            } else {
                $registry->register($app->make(FakeLocalRuAiProvider::class));
                $registry->register($app->make(FakeExternalSanitizedAiProvider::class));
            }

            return $registry;
        });

        $this->app->bind(FileScannerInterface::class, fn ($app) => match (config('ai-price-lists.scanner')) {
            'clamav' => $app->make(ClamAvFileScanner::class),
            default => $app->make(NullFileScanner::class),
        });
        $this->app->bind(StructuredTextModelProviderInterface::class, fn ($app) => match (config('ai-price-lists.ai.provider')) {
            'fake' => $app->make(FakeStructuredTextModelProvider::class),
            default => $app->make(YandexAiStudioProvider::class),
        });
        $this->app->bind(OcrProviderInterface::class, fn ($app) => match (config('ai-price-lists.ai.provider')) {
            'fake' => $app->make(FakeOcrProvider::class),
            default => $app->make(YandexVisionOcrProvider::class),
        });

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
        MailMessageAttachment::observe(MailMessageAttachmentObserver::class);

        RateLimiter::for('bank-oauth', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-sync', fn (Request $request) => Limit::perMinute(2)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-reconcile', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('bank-drafts', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('price-list-ai', fn () => Limit::perMinute(
            max(1, (int) config('ai-price-lists.ai.requests_per_minute', 30))
        )->by('yandex-price-list-ai'));
        RateLimiter::for('ai-sales', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        Gate::before(function ($user, string $ability) {
            if (Str::startsWith($ability, 'ai_price_lists.')) {
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
        Gate::policy(PriceListImport::class, PriceListImportPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(UnitBusinessContext::class, UnitBusinessContextPolicy::class);
        Gate::policy(UnitObservation::class, UnitObservationPolicy::class);
        Gate::policy(EntityCandidateProposal::class, EntityCandidateProposalPolicy::class);
        Gate::policy(AiAgentDefinition::class, AiAgentDefinitionPolicy::class);
        Gate::policy(AiAgentRun::class, AiAgentRunPolicy::class);

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
