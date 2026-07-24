<?php

namespace App\Jobs;

use App\Models\GoodStockAlert;
use App\Services\Goods\GoodStockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateGoodStockAvailabilityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $goodId) {}

    public function handle(GoodStockService $stock): void
    {
        if (! $stock->syncAvailability($this->goodId)) {
            return;
        }

        GoodStockAlert::query()
            ->active()
            ->where('good_id', $this->goodId)
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $alertId) => SendGoodStockAlertNotificationJob::dispatch($alertId));
    }
}
