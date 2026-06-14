<?php

namespace App\Jobs;

use App\Models\YandexAccount;
use App\Services\Yandex\YandexDirectReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncYandexDirectStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public string $from,
        public string $to,
        public ?int $accountId = null,
    ) {}

    public function handle(YandexDirectReportService $service): void
    {
        $account = $this->accountId ? YandexAccount::query()->find($this->accountId) : null;

        $service->syncPeriod($this->from, $this->to, $account);
    }
}
