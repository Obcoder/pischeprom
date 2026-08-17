<?php

namespace App\Jobs;

use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUnisenderWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(public array $eventIds)
    {
        $this->eventIds = array_values(array_unique(array_map('intval', $eventIds)));
    }

    public function handle(UnisenderWebhookService $service): void
    {
        $service->processStoredEventIds($this->eventIds);
    }
}
