<?php

namespace App\Jobs;

use App\Services\CommercialOffers\MailingCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMailingCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $campaignId, public ?int $userId = null) {}

    public function handle(MailingCampaignService $service): void
    {
        $service->startSending($this->campaignId, $this->userId);
    }
}
