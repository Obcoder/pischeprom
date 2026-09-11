<?php

namespace App\Observers;

use App\Jobs\EvaluateGoodStockAvailabilityJob;
use App\Models\GoodStockMovement;
use Illuminate\Support\Facades\DB;

class GoodStockMovementObserver
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
        // Capture the previous good_id during the event: Eloquent resets originals
        // before deferred observers run. Queue both goods only after a successful commit.
        $goodIds = collect($goodIds)
            ->filter()
            ->map(fn ($goodId) => (int) $goodId)
            ->unique()
            ->all();

        DB::afterCommit(function () use ($goodIds): void {
            foreach ($goodIds as $goodId) {
                EvaluateGoodStockAvailabilityJob::dispatch($goodId);
            }
        });
    }
}
