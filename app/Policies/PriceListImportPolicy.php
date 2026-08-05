<?php

namespace App\Policies;

use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Models\PriceListImport;
use App\Models\User;

class PriceListImportPolicy
{
    public function viewAny(?User $user): bool
    {
        return $this->can($user, 'ai_price_lists.view');
    }

    public function view(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.view');
    }

    public function download(?User $user, PriceListImport $import): bool
    {
        if (! $this->can($user, 'ai_price_lists.view')) {
            return false;
        }

        if ($import->status !== PriceListStatus::Quarantined) {
            return true;
        }

        return config('ai-price-lists.authorization_enabled')
            && $this->can($user, 'ai_price_lists.view_technical');
    }

    public function reprocess(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.process');
    }

    public function review(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.review')
            && in_array($import->status, [
                PriceListStatus::SupplierUnresolved,
                PriceListStatus::ReviewRequired,
                PriceListStatus::ReadyToApply,
                PriceListStatus::PartiallyApplied,
            ], true);
    }

    public function assignSupplier(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.assign_supplier')
            && in_array($import->status, [
                PriceListStatus::AwaitingClassification,
                PriceListStatus::SupplierUnresolved,
                PriceListStatus::ReviewRequired,
                PriceListStatus::ReadyToApply,
                PriceListStatus::PartiallyApplied,
            ], true);
    }

    public function apply(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.apply')
            && in_array($import->status, [
                PriceListStatus::ReadyToApply,
                PriceListStatus::PartiallyApplied,
            ], true);
    }

    public function viewTechnical(?User $user, PriceListImport $import): bool
    {
        return $this->can($user, 'ai_price_lists.view_technical');
    }

    private function can(?User $user, string $permission): bool
    {
        return ! config('ai-price-lists.authorization_enabled')
            || (bool) $user?->can($permission);
    }
}
