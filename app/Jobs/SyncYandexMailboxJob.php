<?php

namespace App\Jobs;

use App\Services\Mail\YandexMailboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncYandexMailboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $limit = 1000)
    {
    }

    public function handle(YandexMailboxService $service): void
    {
        $service->syncAll($this->limit);
    }
}
