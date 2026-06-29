<?php

namespace App\Services\Gis;

use App\Models\Entity;
use App\Models\EntityLocation;
use App\Services\Gis\DTO\GeocodeResult;
use App\Services\Gis\Exceptions\GisValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EntityLocationService
{
    private const LOCATION_RELATIONS = [
        'location',
        'classification',
        'cities',
        'buildings.city',
        'users',
    ];

    public function __construct(private readonly GisService $gis) {}

    public function entitiesWithLocations(array $filters = []): Collection
    {
        return $this->baseFilteredQuery($filters)
            ->whereHas('location', function (Builder $query) use ($filters) {
                $query->whereNotNull('lat')
                    ->whereNotNull('lon');

                $this->applyBbox($query, $filters);
            })
            ->with(self::LOCATION_RELATIONS)
            ->limit($this->limit($filters, 1000, 5000))
            ->get()
            ->map(fn (Entity $entity) => $this->mapEntityItem($entity))
            ->values();
    }

    public function entitiesWithoutLocations(array $filters = []): Collection
    {
        return $this->baseFilteredQuery($filters)
            ->where(function (Builder $query) {
                $query->doesntHave('location')
                    ->orWhereHas('location', function (Builder $locationQuery) {
                        $locationQuery->whereNull('lat')->orWhereNull('lon');
                    });
            })
            ->with(self::LOCATION_RELATIONS)
            ->orderBy('name')
            ->limit($this->limit($filters, 200, 1000))
            ->get()
            ->map(fn (Entity $entity) => $this->mapEntityItem($entity, includeCoordinates: false))
            ->values();
    }

    public function locationPayload(Entity $entity): array
    {
        $entity->loadMissing(self::LOCATION_RELATIONS);

        return [
            'entity' => $this->mapEntityItem($entity, includeCoordinates: false),
            'location' => $entity->location ? $this->mapLocation($entity->location) : null,
        ];
    }

    public function saveManualLocation(Entity $entity, array $payload): EntityLocation
    {
        [$lat, $lon] = $this->gis->assertCoordinates($payload['lat'] ?? null, $payload['lon'] ?? null);

        $location = EntityLocation::query()->updateOrCreate(
            ['entity_id' => $entity->id],
            [
                'address_text' => filled($payload['address_text'] ?? null)
                    ? trim((string) $payload['address_text'])
                    : $this->resolveEntityAddress($entity),
                'lat' => $lat,
                'lon' => $lon,
                'source' => 'manual',
                'provider_object_id' => null,
                'precision_level' => $this->normalizePrecision($payload['precision_level'] ?? 'exact'),
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ],
        );

        $this->syncGeoPoint($location->entity_id);

        return $location->refresh();
    }

    public function geocode(Entity $entity, ?string $provider, ?string $address = null): GeocodeResult
    {
        $address = trim((string) ($address ?: $this->resolveEntityAddress($entity)));

        if ($address === '') {
            throw new GisValidationException('У entity нет адреса для геокодирования. Заполните legal_address или связанный building.');
        }

        return $this->gis->provider($provider)->geocode($address, [
            'limit' => 7,
        ]);
    }

    public function resolveEntityAddress(Entity $entity): ?string
    {
        $entity->loadMissing(['buildings.city', 'cities']);

        if (filled($entity->legal_address)) {
            return trim((string) $entity->legal_address);
        }

        $building = $entity->buildings->first(fn ($item) => filled($item->address));

        if ($building) {
            $cityName = trim((string) $building->city?->name);
            $address = trim((string) $building->address);

            if ($cityName !== '' && ! str_contains(mb_strtolower($address), mb_strtolower($cityName))) {
                return $cityName.', '.$address;
            }

            return $address;
        }

        $city = $entity->cities->first(fn ($item) => filled($item->name));

        return $city?->name;
    }

    public function mapEntityItem(Entity $entity, bool $includeCoordinates = true): array
    {
        $entity->loadMissing(self::LOCATION_RELATIONS);

        $location = $entity->location;
        $primaryUser = $entity->users->first(fn ($user) => (bool) ($user->pivot?->is_primary ?? false))
            ?: $entity->users->first();

        $payload = [
            'entity_id' => $entity->id,
            'name' => $entity->name,
            'type' => $entity->classification?->name,
            'status' => $primaryUser?->pivot?->status,
            'address' => $location?->address_text ?: $this->resolveEntityAddress($entity),
            'city' => $entity->cities->first()?->name,
            'manager_id' => $primaryUser?->id,
            'manager_name' => $primaryUser?->name,
        ];

        if (! $includeCoordinates) {
            return [
                ...$payload,
                'lat' => $location?->lat,
                'lon' => $location?->lon,
                'source' => $location?->source,
                'is_confirmed' => (bool) ($location?->is_confirmed ?? false),
            ];
        }

        return [
            ...$payload,
            'lat' => $location?->lat,
            'lon' => $location?->lon,
            'source' => $location?->source,
            'is_confirmed' => (bool) ($location?->is_confirmed ?? false),
        ];
    }

    public function mapLocation(EntityLocation $location): array
    {
        return [
            'entity_id' => $location->entity_id,
            'address_text' => $location->address_text,
            'lat' => $location->lat,
            'lon' => $location->lon,
            'source' => $location->source,
            'provider_object_id' => $location->provider_object_id,
            'precision_level' => $location->precision_level,
            'is_confirmed' => $location->is_confirmed,
            'geocoded_at' => $location->geocoded_at?->toISOString(),
            'confirmed_at' => $location->confirmed_at?->toISOString(),
            'created_at' => $location->created_at?->toISOString(),
            'updated_at' => $location->updated_at?->toISOString(),
        ];
    }

    private function baseFilteredQuery(array $filters): Builder
    {
        return Entity::query()
            ->search($filters['search'] ?? null)
            ->when(filled($filters['type'] ?? null), function (Builder $query) use ($filters) {
                $types = $this->listFilter($filters['type']);

                $query->where(function (Builder $typeQuery) use ($types) {
                    $ids = array_values(array_filter($types, fn ($value) => is_numeric($value)));
                    $names = array_values(array_filter($types, fn ($value) => ! is_numeric($value)));

                    if ($ids !== []) {
                        $typeQuery->whereIn('entity_classification_id', array_map('intval', $ids));
                    }

                    if ($names !== []) {
                        $typeQuery->orWhereHas('classification', function (Builder $classificationQuery) use ($names) {
                            foreach ($names as $name) {
                                $classificationQuery->orWhere('name', 'like', '%'.$name.'%');
                            }
                        });
                    }
                });
            })
            ->when(filled($filters['status'] ?? null), function (Builder $query) use ($filters) {
                $statuses = $this->listFilter($filters['status']);

                $query->whereHas('users', fn (Builder $userQuery) => $userQuery->whereIn('entity_user.status', $statuses));
            })
            ->when(filled($filters['city'] ?? null), function (Builder $query) use ($filters) {
                $cities = $this->listFilter($filters['city']);

                $query->whereHas('cities', function (Builder $cityQuery) use ($cities) {
                    $ids = array_values(array_filter($cities, fn ($value) => is_numeric($value)));
                    $names = array_values(array_filter($cities, fn ($value) => ! is_numeric($value)));

                    if ($ids !== []) {
                        $cityQuery->whereIn('cities.id', array_map('intval', $ids));
                    }

                    if ($names !== []) {
                        foreach ($names as $name) {
                            $cityQuery->orWhere('cities.name', 'like', '%'.$name.'%');
                        }
                    }
                });
            })
            ->when(filled($filters['manager_id'] ?? null), function (Builder $query) use ($filters) {
                $managerIds = array_map('intval', $this->listFilter($filters['manager_id']));

                $query->whereHas('users', fn (Builder $userQuery) => $userQuery->whereIn('users.id', $managerIds));
            });
    }

    private function applyBbox(Builder $query, array $filters): void
    {
        $north = $filters['north'] ?? null;
        $south = $filters['south'] ?? null;
        $east = $filters['east'] ?? null;
        $west = $filters['west'] ?? null;

        if ($north === null || $south === null || $east === null || $west === null) {
            return;
        }

        if (! is_numeric($north) || ! is_numeric($south) || ! is_numeric($east) || ! is_numeric($west)) {
            throw new GisValidationException('bbox должен содержать числовые north, south, east, west.');
        }

        $query->whereBetween('lat', [(float) $south, (float) $north])
            ->whereBetween('lon', [(float) $west, (float) $east]);
    }

    private function listFilter(mixed $value): array
    {
        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }

        return collect((array) $value)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();
    }

    private function limit(array $filters, int $default, int $max): int
    {
        return min(max((int) ($filters['limit'] ?? $default), 1), $max);
    }

    private function normalizePrecision(mixed $precision): string
    {
        $precision = strtolower(trim((string) $precision));

        return in_array($precision, ['exact', 'building', 'street', 'city', 'unknown'], true)
            ? $precision
            : 'unknown';
    }

    private function syncGeoPoint(int $entityId): void
    {
        if (! Schema::hasColumn('entity_locations', 'geo_point')) {
            return;
        }

        DB::table('entity_locations')
            ->where('entity_id', $entityId)
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->update([
                'geo_point' => DB::raw("ST_GeomFromText(CONCAT('POINT(', lon, ' ', lat, ')'), 4326)"),
            ]);
    }
}
