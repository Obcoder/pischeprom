<?php

namespace App\Console\Commands;

use App\Models\YandexAccount;
use App\Services\Yandex\YandexDirectCampaignService;
use Illuminate\Console\Command;

class SyncYandexDirectStatusesCommand extends Command
{
    protected $signature = 'yandex:direct:sync-statuses {--account=}';

    protected $description = 'Sync Yandex Direct campaign/ad statuses';

    public function handle(YandexDirectCampaignService $service): int
    {
        $account = $this->option('account') ? YandexAccount::query()->find((int) $this->option('account')) : null;
        $result = $service->syncStatuses($account);

        $this->info($result['message']);
        $this->info('Synced: ' . $result['synced']);

        return self::SUCCESS;
    }
}
