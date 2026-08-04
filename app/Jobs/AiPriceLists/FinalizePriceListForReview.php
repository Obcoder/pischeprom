<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\MaxPriceListNotifier;
use App\Domain\AiPriceLists\Services\PriceListAuditLogger;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Models\PriceListImport;

class FinalizePriceListForReview extends AbstractPriceListJob
{
    public function handle(PriceListStateMachine $states, PriceListAuditLogger $audit, MaxPriceListNotifier $notifier): void
    {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if ($import->status !== PriceListStatus::Matching) {
            return;
        }

        $target = $import->entity_id ? PriceListStatus::ReviewRequired : PriceListStatus::SupplierUnresolved;
        $states->transition($import, $target, PriceListStage::Finalize, 100);
        $audit->record($import, 'review_ready', [
            'supplier_resolved' => $import->entity_id !== null,
            'items_total' => $import->items_total,
        ], stage: PriceListStage::Finalize->value);
        $notifier->ready($import->refresh());
    }
}
