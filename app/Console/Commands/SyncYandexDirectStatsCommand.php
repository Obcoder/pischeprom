<?php

namespace App\Console\Commands;

use App\Models\YandexAccount;
use App\Services\Yandex\YandexDirectReportService;
use Illuminate\Console\Command;

class SyncYandexDirectStatsCommand extends Command
{
    protected $signature = 'yandex:direct:sync-stats {--from=} {--to=} {--account=}';

    protected $description = 'Sync Yandex Direct daily stats';

    public function handle(YandexDirectReportService $service): int
    {
        $from = $this->option('from') ?: now()->subDay()->toDateString();
        $to = $this->option('to') ?: $from;
        $account = $this->option('account') ? YandexAccount::query()->find((int) $this->option('account')) : null;

        $result = $service->syncPeriod($from, $to, $account);
        $this->info('Accounts: ' . $result['accounts']);
        $this->info('Stored rows: ' . $result['stored']);

        return self::SUCCESS;
    }
}
