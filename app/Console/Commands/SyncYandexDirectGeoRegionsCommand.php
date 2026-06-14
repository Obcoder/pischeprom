<?php

namespace App\Console\Commands;

use App\Services\Yandex\YandexDirectGeoRegionService;
use Illuminate\Console\Command;
use Throwable;

class SyncYandexDirectGeoRegionsCommand extends Command
{
    protected $signature = 'yandex:direct:sync-geo-regions';

    protected $description = 'Sync Yandex Direct geo regions dictionary';

    public function handle(YandexDirectGeoRegionService $service): int
    {
        try {
            $result = $service->syncAll();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Stored: {$result['stored']}; total cached: {$result['total']}");

        return self::SUCCESS;
    }
}
