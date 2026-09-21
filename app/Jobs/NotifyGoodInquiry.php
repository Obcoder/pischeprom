<?php

namespace App\Jobs;

use App\Services\Goods\GoodInquiryNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyGoodInquiry implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $inquiryId) {}

    public function handle(GoodInquiryNotificationService $notifications): void
    {
        $notifications->deliver($this->inquiryId);
    }
}
