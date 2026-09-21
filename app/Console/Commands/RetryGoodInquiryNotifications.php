<?php

namespace App\Console\Commands;

use App\Models\GoodInquiry;
use App\Services\Goods\GoodInquiryNotificationService;
use Illuminate\Console\Command;

class RetryGoodInquiryNotifications extends Command
{
    protected $signature = 'goods:retry-inquiry-notifications {--limit=20}';

    protected $description = 'Deliver pending public product inquiries, including after queue or mail outages';

    public function handle(GoodInquiryNotificationService $notifications): int
    {
        $ids = GoodInquiry::query()->whereNotNull('next_notification_at')
            ->where('next_notification_at', '<=', now())
            ->orderBy('next_notification_at')
            ->limit(max(1, min(100, (int) $this->option('limit'))))
            ->pluck('id');

        foreach ($ids as $id) {
            $notifications->deliver($id);
        }

        $this->info('Processed inquiries: '.$ids->count());

        return self::SUCCESS;
    }
}
