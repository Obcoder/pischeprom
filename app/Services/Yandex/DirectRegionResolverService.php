<?php

namespace App\Services\Yandex;

use App\Models\Region;
use Illuminate\Support\Facades\Schema;

class DirectRegionResolverService
{
    public function regionIds(): array
    {
        $dbIds = $this->databaseRegionIds();

        if ($dbIds) {
            return $dbIds;
        }

        return collect(config('yandex.direct.auto_launch.region_ids') ?: config('yandex.default_region_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function source(): string
    {
        return $this->databaseRegionIds() ? 'database' : 'env';
    }

    private function databaseRegionIds(): array
    {
        if (
            !Schema::hasColumn('regions', 'yandex_direct_region_ids')
            || !Schema::hasColumn('regions', 'use_for_yandex_direct')
        ) {
            return [];
        }

        return Region::query()
            ->where('use_for_yandex_direct', true)
            ->whereNotNull('yandex_direct_region_ids')
            ->get(['yandex_direct_region_ids'])
            ->flatMap(fn (Region $region) => $region->yandex_direct_region_ids ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
