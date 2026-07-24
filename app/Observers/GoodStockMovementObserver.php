<?php

namespace App\Observers;

use App\Jobs\EvaluateGoodStockAvailabilityJob;
use App\Models\GoodStockMovement;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class GoodStockMovementObserver implements ShouldHandleEventsAfterCommit
{
    public function created(GoodStockMovement $movement): void
    {
        $this->dispatch([$movement->good_id]);
    }

    public function updated(GoodStockMovement $movement): void
    {
        $this->dispatch([
            $movement->good_id,
            $movement->getOriginal('good_id'),
        ]);
    }

    public function deleted(GoodStockMovement $movement): void
    {
        $this->dispatch([
            $movement->good_id,
            $movement->getOriginal('good_id'),
        ]);
    }

    private function dispatch(array $goodIds): void
    {
        collect($goodIds)
            ->filter()
            ->map(fn ($goodId) => (int) $goodId)
            ->unique()
            ->each(fn (int $goodId) => EvaluateGoodStockAvailabilityJob::dispatch($goodId));
    }
}
