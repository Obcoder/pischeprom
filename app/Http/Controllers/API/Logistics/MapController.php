<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\MapFeaturesRequest;
use App\Models\LogisticsTrip;
use App\Services\Logistics\Map\GeoJsonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MapController extends Controller
{
    public function __construct(private readonly GeoJsonFactory $geoJson) {}

    public function __invoke(MapFeaturesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $bounds = $request->bounds();
        $layers = $validated['layers'] ?? ['cities', 'trips'];
        $limit = $this->limitForZoom(
            (int) ($validated['limit'] ?? config('logistics.map.max_features', 1000)),
            (float) $validated['zoom'],
        );

        $cities = in_array('cities', $layers, true)
            ? $this->cities($bounds, $limit)
            : ['collection' => $this->geoJson->featureCollection([]), 'truncated' => false];
        $trips = in_array('trips', $layers, true)
            ? $this->trips($bounds, $validated)
            : ['items' => [], 'truncated' => false];
        $entities = in_array('entities', $layers, true)
            ? $this->entities($bounds, $limit)
            : ['collection' => $this->geoJson->featureCollection([]), 'truncated' => false, 'available' => Schema::hasTable('entity_locations')];

        return response()->json(['data' => [
            'cities' => $cities['collection'],
            'trips' => $trips['items'],
            'entities' => $entities['collection'],
            'meta' => [
                'bbox' => array_values($bounds),
                'zoom' => (float) $validated['zoom'],
                'limit' => $limit,
                'truncated' => [
                    'cities' => $cities['truncated'],
                    'trips' => $trips['truncated'],
                    'entities' => $entities['truncated'],
                ],
                'entity_layer_available' => $entities['available'],
                'missing_coordinates' => $this->missingCoordinateCounts(),
            ],
        ]]);
    }

    /** @param array{west: float, south: float, east: float, north: float} $bounds */
    private function cities(array $bounds, int $limit): array
    {
        $query = DB::table('logistics_cities')
            ->join('cities', 'cities.id', '=', 'logistics_cities.city_id')
            ->leftJoin('regions', 'regions.id', '=', 'cities.region_id')
            ->whereNotNull('logistics_cities.routing_latitude')
            ->whereNotNull('logistics_cities.routing_longitude')
            ->whereNotNull('logistics_cities.coordinates_verified_at')
            ->whereBetween('logistics_cities.routing_latitude', [$bounds['south'], $bounds['north']]);
        $this->applyLongitudeBounds($query, 'logistics_cities.routing_longitude', $bounds);
        $rows = $query
            ->orderBy('cities.name')
            ->limit($limit + 1)
            ->get([
                'cities.id', 'cities.name', 'regions.name as region_name',
                'logistics_cities.routing_latitude', 'logistics_cities.routing_longitude',
                'logistics_cities.is_matrix_enabled', 'logistics_cities.coordinate_source',
            ]);
        $truncated = $rows->count() > $limit;

        return [
            'collection' => $this->geoJson->featureCollection($rows->take($limit)->map(fn (object $row): array => $this->geoJson->point(
                (float) $row->routing_longitude,
                (float) $row->routing_latitude,
                [
                    'kind' => 'city',
                    'city_id' => (int) $row->id,
                    'name' => $row->name,
                    'region' => $row->region_name,
                    'is_matrix_enabled' => (bool) $row->is_matrix_enabled,
                    'coordinate_source' => $row->coordinate_source,
                ],
                'city-'.$row->id,
            ))->all()),
            'truncated' => $truncated,
        ];
    }

    /** @param array{west: float, south: float, east: float, north: float} $bounds */
    private function trips(array $bounds, array $validated): array
    {
        $limit = max(1, (int) config('logistics.map.max_trips', 100));
        $query = LogisticsTrip::query()
            ->select([
                'id', 'number', 'status', 'vehicle_id', 'planned_departure_at',
                'actual_departure_at', 'route_calculated_at',
            ])
            ->with([
                'vehicle:id,name,registration_number',
                'stops:id,trip_id,sequence,city_id,address,latitude,longitude',
                'stops.city:id,name',
                'currentRoute:id,trip_id,is_current,status,distance_m,duration_s,osm_data_version,calculated_at',
            ])
            ->withExists([
                'currentRoute as current_route_geometry_available' => fn (Builder $query) => $query
                    ->whereNotNull('shape_polyline6')
                    ->where('shape_polyline6', '<>', ''),
            ])
            ->whereHas('stops', function (Builder $query) use ($bounds): void {
                $query->whereBetween('latitude', [$bounds['south'], $bounds['north']]);
                $this->applyEloquentLongitudeBounds($query, 'longitude', $bounds);
            })
            ->when(! empty($validated['trip_ids']), fn (Builder $query) => $query->whereIn('id', $validated['trip_ids']))
            ->when(! empty($validated['status']), fn (Builder $query) => $query->whereIn('status', $validated['status']))
            ->when(! empty($validated['vehicle_id']), fn (Builder $query) => $query->where('vehicle_id', $validated['vehicle_id']))
            ->when(! empty($validated['city_id']), fn (Builder $query) => $query->whereHas(
                'stops', fn (Builder $stops) => $stops->where('city_id', $validated['city_id'])
            ));

        if (! empty($validated['date_from'])) {
            $query->where(function (Builder $query) use ($validated): void {
                $query->where('actual_departure_at', '>=', $validated['date_from'])
                    ->orWhere(function (Builder $query) use ($validated): void {
                        $query->whereNull('actual_departure_at')
                            ->where('planned_departure_at', '>=', $validated['date_from']);
                    });
            });
        }
        if (! empty($validated['date_to'])) {
            $query->where(function (Builder $query) use ($validated): void {
                $query->where('actual_departure_at', '<=', $validated['date_to'].' 23:59:59')
                    ->orWhere(function (Builder $query) use ($validated): void {
                        $query->whereNull('actual_departure_at')
                            ->where('planned_departure_at', '<=', $validated['date_to'].' 23:59:59');
                    });
            });
        }

        $rows = $query->orderByDesc('actual_departure_at')->orderByDesc('id')->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;

        return [
            'items' => $rows->take($limit)->map(fn (LogisticsTrip $trip): array => [
                'id' => $trip->id,
                'number' => $trip->number,
                'status' => $trip->status?->value,
                'vehicle' => $trip->vehicle ? [
                    'id' => $trip->vehicle->id,
                    'name' => $trip->vehicle->name,
                    'registration_number' => $trip->vehicle->registration_number,
                ] : null,
                'departure_at' => ($trip->actual_departure_at ?: $trip->planned_departure_at)?->toISOString(),
                'route_summary' => $trip->stops->pluck('city.name')->filter()->join(' → '),
                'stops_count' => $trip->stops->count(),
                'current_route' => $trip->currentRoute ? [
                    'id' => $trip->currentRoute->id,
                    'status' => $trip->currentRoute->status?->value,
                    'distance_m' => $trip->currentRoute->distance_m,
                    'duration_s' => $trip->currentRoute->duration_s,
                    'osm_data_version' => $trip->currentRoute->osm_data_version,
                    'calculated_at' => $trip->currentRoute->calculated_at?->toISOString(),
                    'geometry_available' => (bool) $trip->current_route_geometry_available,
                ] : null,
                'map_url' => route('api.logistics.trips.map', ['trip' => $trip->id], false),
            ])->values()->all(),
            'truncated' => $truncated,
        ];
    }

    /** @param array{west: float, south: float, east: float, north: float} $bounds */
    private function entities(array $bounds, int $limit): array
    {
        if (! Schema::hasTable('entity_locations')) {
            return [
                'collection' => $this->geoJson->featureCollection([]),
                'truncated' => false,
                'available' => false,
            ];
        }

        $query = DB::table('entity_locations')
            ->join('entities', 'entities.id', '=', 'entity_locations.entity_id')
            ->whereNotNull('entity_locations.lat')
            ->whereNotNull('entity_locations.lon')
            ->whereBetween('entity_locations.lat', [$bounds['south'], $bounds['north']]);
        $this->applyLongitudeBounds($query, 'entity_locations.lon', $bounds);
        $rows = $query->orderBy('entities.name')->limit($limit + 1)->get([
            'entity_locations.entity_id', 'entity_locations.address_text',
            'entity_locations.lat', 'entity_locations.lon', 'entity_locations.is_confirmed',
            'entity_locations.precision_level', 'entities.name',
        ]);
        $truncated = $rows->count() > $limit;

        return [
            'collection' => $this->geoJson->featureCollection($rows->take($limit)->map(fn (object $row): array => $this->geoJson->point(
                (float) $row->lon,
                (float) $row->lat,
                [
                    'kind' => 'entity',
                    'entity_id' => (int) $row->entity_id,
                    'name' => $row->name,
                    'address' => $row->address_text,
                    'is_confirmed' => (bool) $row->is_confirmed,
                    'precision_level' => $row->precision_level,
                ],
                'entity-'.$row->entity_id,
            ))->all()),
            'truncated' => $truncated,
            'available' => true,
        ];
    }

    private function limitForZoom(int $requested, float $zoom): int
    {
        $configured = max(1, (int) config('logistics.map.max_features', 1000));
        $zoomLimit = $zoom < 4 ? 250 : ($zoom < 7 ? 500 : $configured);

        return max(1, min($requested, $configured, $zoomLimit));
    }

    /** @return array{cities: int, trip_stops: int, entities: int|null} */
    private function missingCoordinateCounts(): array
    {
        return Cache::remember('logistics:map:missing-coordinate-counts:v2', 300, function (): array {
            $entitiesAvailable = Schema::hasTable('entity_locations');

            return [
                'cities' => DB::table('logistics_cities')
                    ->where(function (QueryBuilder $query): void {
                        $query->whereNull('routing_latitude')
                            ->orWhereNull('routing_longitude')
                            ->orWhereNull('coordinates_verified_at');
                    })->count(),
                'trip_stops' => DB::table('logistics_trip_stops')
                    ->where(function (QueryBuilder $query): void {
                        $query->whereNull('latitude')->orWhereNull('longitude');
                    })->count(),
                'entities' => $entitiesAvailable
                    ? DB::table('entities')
                        ->leftJoin('entity_locations', 'entity_locations.entity_id', '=', 'entities.id')
                        ->where(function (QueryBuilder $query): void {
                            $query->whereNull('entity_locations.entity_id')
                                ->orWhereNull('entity_locations.lat')
                                ->orWhereNull('entity_locations.lon');
                        })->count()
                    : null,
            ];
        });
    }

    /** @param array{west: float, south: float, east: float, north: float} $bounds */
    private function applyLongitudeBounds(QueryBuilder $query, string $column, array $bounds): void
    {
        if ($bounds['west'] < $bounds['east']) {
            $query->whereBetween($column, [$bounds['west'], $bounds['east']]);

            return;
        }

        $query->where(function (QueryBuilder $query) use ($column, $bounds): void {
            $query->where($column, '>=', $bounds['west'])
                ->orWhere($column, '<=', $bounds['east']);
        });
    }

    /** @param array{west: float, south: float, east: float, north: float} $bounds */
    private function applyEloquentLongitudeBounds(Builder $query, string $column, array $bounds): void
    {
        if ($bounds['west'] < $bounds['east']) {
            $query->whereBetween($column, [$bounds['west'], $bounds['east']]);

            return;
        }

        $query->where(function (Builder $query) use ($column, $bounds): void {
            $query->where($column, '>=', $bounds['west'])
                ->orWhere($column, '<=', $bounds['east']);
        });
    }
}
