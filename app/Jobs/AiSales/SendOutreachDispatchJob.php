<?php

namespace App\Jobs\AiSales;

use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Services\CommercialOffers\UnisenderRequestProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendOutreachDispatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout = 60;

    public function __construct(public readonly int $outreachDispatchId)
    {
        $this->tries = UnisenderRequestProfile::OutreachZeroRetry->queueTries();
        $this->onQueue('outreach-mail');
    }

    public function handle(OutreachDispatchService $service): void
    {
        $service->deliver($this->outreachDispatchId);
    }
}
