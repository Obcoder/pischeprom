<?php

namespace App\Jobs\AiPriceLists;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Enums\PriceListStage;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Services\ApplyPriceListService;
use App\Domain\AiPriceLists\Services\PriceListStateMachine;
use App\Models\PriceListImport;
use App\Models\User;

class ApplyConfirmedPriceList extends AbstractPriceListJob
{
    /** @param list<int> $selectedItemIds */
    public function __construct(int $importId, public readonly int $userId, public readonly array $selectedItemIds = [])
    {
        parent::__construct($importId);
    }

    public function uniqueId(): string
    {
        return 'price-list-apply:'.$this->importId;
    }

    public function handle(ApplyPriceListService $application, PriceListStateMachine $states): void
    {
        $import = PriceListImport::query()->findOrFail($this->importId);

        if ($import->status === PriceListStatus::Applied) {
            return;
        }

        if (! in_array($import->status, [PriceListStatus::ReadyToApply, PriceListStatus::PartiallyApplied, PriceListStatus::Applying, PriceListStatus::Failed], true)) {
            return;
        }

        $user = User::query()->findOrFail($this->userId);

        if ($import->status !== PriceListStatus::Applying) {
            $import = $states->transition($import, PriceListStatus::Applying, PriceListStage::Apply, 95, user: $user);
        }

        $application->apply($import, $user, $this->selectedItemIds);
        $import->refresh();
        $hasRemaining = $import->items()
            ->whereIn('decision_status', [ItemDecisionStatus::Matched->value, ItemDecisionStatus::CreateDraft->value])
            ->whereNull('applied_at')
            ->exists();
        $states->transition($import, $hasRemaining ? PriceListStatus::PartiallyApplied : PriceListStatus::Applied, PriceListStage::Apply, 100, user: $user);
    }
}
