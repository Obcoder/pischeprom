<?php

namespace App\Services\Yandex;

use App\Models\YandexAccount;
use App\Models\YandexDirectGeoRegion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class YandexDirectGeoRegionService
{
    public function __construct(private readonly YandexDirectApiClient $client) {}

    public function search(string $search = '', int $limit = 40, bool $remote = true, ?YandexAccount $account = null): array
    {
        $limit = max(1, min($limit, 100));
        $search = trim($search);
        $source = 'cache';
        $warning = null;

        if ($remote && $search !== '') {
            try {
                $items = ctype_digit($search)
                    ? $this->fetchByIds([(int) $search], $account)
                    : $this->fetchBySearch($search, $limit, $account);
                $this->storeMany($items);
                $source = 'api';
            } catch (Throwable $e) {
                $warning = $e->getMessage();
            }
        }

        return [
            'items' => $this->cachedSearch($search, $limit)->map(fn (YandexDirectGeoRegion $region) => $this->serialize($region))->values()->all(),
            'source' => $source,
            'warning' => $warning,
        ];
    }

    public function findByIds(array $ids, bool $remote = true, ?YandexAccount $account = null): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $cached = $this->cachedByIds($ids->all());
        $missing = $ids->diff($cached->pluck('external_region_id'));

        if ($remote && $missing->isNotEmpty()) {
            $this->storeMany($this->fetchByIds($missing->all(), $account));
            $cached = $this->cachedByIds($ids->all());
        }

        return $ids
            ->map(fn (int $id) => $cached->firstWhere('external_region_id', $id))
            ->filter()
            ->map(fn (YandexDirectGeoRegion $region) => $this->serialize($region))
            ->values()
            ->all();
    }

    public function syncAll(?YandexAccount $account = null): array
    {
        $this->ensureTableExists();

        $payload = $this->client->request($account ?: $this->activeAccount(), 'dictionaries', 'get', [
            'DictionaryNames' => ['GeoRegions'],
        ]);

        $items = Arr::get($payload, 'result.GeoRegions', []);
        $stored = $this->storeMany(is_array($items) ? $items : []);

        return [
            'stored' => $stored,
            'total' => YandexDirectGeoRegion::query()->count(),
            'synced_at' => now(),
        ];
    }

    public function storeMany(array $items): int
    {
        if (!$items) {
            return 0;
        }

        $this->ensureTableExists();

        $now = now();
        $rows = collect($items)
            ->map(fn (array $item) => $this->normalize($item, $now))
            ->filter(fn (?array $item) => $item !== null)
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        YandexDirectGeoRegion::query()->upsert(
            $rows->all(),
            ['external_region_id'],
            ['parent_id', 'name', 'type', 'parent_names', 'raw', 'synced_at', 'updated_at']
        );

        return $rows->count();
    }

    public function serialize(YandexDirectGeoRegion $region): array
    {
        $parentNames = $region->parent_names ?: [];

        return [
            'id' => $region->id,
            'external_region_id' => $region->external_region_id,
            'parent_id' => $region->parent_id,
            'name' => $region->name,
            'type' => $region->type,
            'parent_names' => $parentNames,
            'path' => collect($parentNames)->push($region->name)->filter()->implode(' / '),
            'synced_at' => $region->synced_at,
        ];
    }

    private function fetchByName(string $name, int $limit, ?YandexAccount $account = null): array
    {
        $payload = $this->client->request($account ?: $this->activeAccount(), 'dictionaries', 'getGeoRegions', [
            'SelectionCriteria' => [
                'Name' => $name,
            ],
            'FieldNames' => ['GeoRegionId', 'GeoRegionName', 'ParentGeoRegionNames'],
            'Page' => [
                'Limit' => $limit,
                'Offset' => 0,
            ],
        ]);

        return Arr::get($payload, 'result.GeoRegions', []);
    }

    private function fetchBySearch(string $search, int $limit, ?YandexAccount $account = null): array
    {
        $variants = $this->searchVariants($search);
        $items = [];

        foreach ($variants as $variant) {
            $items = array_merge($items, $this->fetchByExactName($variant, $account));
        }

        $items = array_merge($items, $this->fetchByName($search, $limit, $account));

        return collect($items)
            ->unique(fn (array $item) => (int) Arr::get($item, 'GeoRegionId'))
            ->values()
            ->all();
    }

    private function fetchByExactName(string $name, ?YandexAccount $account = null): array
    {
        $payload = $this->client->request($account ?: $this->activeAccount(), 'dictionaries', 'getGeoRegions', [
            'SelectionCriteria' => [
                'ExactNames' => [$name],
            ],
            'FieldNames' => ['GeoRegionId', 'GeoRegionName', 'ParentGeoRegionNames'],
        ]);

        return Arr::get($payload, 'result.GeoRegions', []);
    }

    private function fetchByIds(array $ids, ?YandexAccount $account = null): array
    {
        $payload = $this->client->request($account ?: $this->activeAccount(), 'dictionaries', 'getGeoRegions', [
            'SelectionCriteria' => [
                'RegionIds' => array_values(array_map('intval', $ids)),
            ],
            'FieldNames' => ['GeoRegionId', 'GeoRegionName', 'ParentGeoRegionNames'],
        ]);

        return Arr::get($payload, 'result.GeoRegions', []);
    }

    private function cachedSearch(string $search, int $limit): EloquentCollection
    {
        $this->ensureTableExists();
        $variants = $this->searchVariants($search);

        return YandexDirectGeoRegion::query()
            ->when($search !== '', function ($query) use ($search, $variants) {
                $query->where(function ($query) use ($search, $variants) {
                    foreach ($variants as $variant) {
                        $query->orWhere('name', 'like', "%{$variant}%");
                    }

                    $query->orWhere('external_region_id', (int) $search);
                });
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$search, $search . '%'])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    private function searchVariants(string $search): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($search)) ?: $search;
        $withoutYo = str_replace(['ё', 'Ё'], ['е', 'Е'], $normalized);

        return collect([
            $normalized,
            $withoutYo,
            str_replace('-', ' ', $normalized),
            str_replace(' ', '-', $normalized),
        ])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cachedByIds(array $ids): Collection
    {
        $this->ensureTableExists();

        return YandexDirectGeoRegion::query()
            ->whereIn('external_region_id', $ids)
            ->get()
            ->keyBy('external_region_id');
    }

    private function normalize(array $item, mixed $now): ?array
    {
        $externalId = (int) (Arr::get($item, 'GeoRegionId') ?? Arr::get($item, 'external_region_id'));

        if ($externalId <= 0) {
            return null;
        }

        $parentNames = Arr::get($item, 'ParentGeoRegionNames.Items', Arr::get($item, 'parent_names', []));
        $parentNames = is_array($parentNames) ? array_values(array_filter($parentNames)) : [];

        return [
            'external_region_id' => $externalId,
            'parent_id' => Arr::get($item, 'ParentId') ?: Arr::get($item, 'parent_id'),
            'name' => (string) (Arr::get($item, 'GeoRegionName') ?: Arr::get($item, 'name') ?: ('Region ' . $externalId)),
            'type' => Arr::get($item, 'GeoRegionType') ?: Arr::get($item, 'type'),
            'parent_names' => json_encode($parentNames, JSON_UNESCAPED_UNICODE),
            'raw' => json_encode($item, JSON_UNESCAPED_UNICODE),
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function activeAccount(): YandexAccount
    {
        $account = YandexAccount::query()
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$account) {
            throw new RuntimeException('Не найден активный Yandex OAuth аккаунт. Подключите Яндекс в разделе Маркетинг → Яндекс.Директ.');
        }

        return $account;
    }

    private function ensureTableExists(): void
    {
        if (!Schema::hasTable('yandex_direct_geo_regions')) {
            throw new RuntimeException('Таблица yandex_direct_geo_regions ещё не создана. Нужно выполнить миграции.');
        }
    }
}
