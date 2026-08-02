<?php

namespace App\Services\Logistics\Map;

use App\Enums\Logistics\DistanceStatus;
use App\Models\LogisticsCityDistance;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RoutingPoint;
use App\Services\Logistics\Routing\Exceptions\NoRouteException;
use App\Services\Logistics\Routing\Support\RoutingHash;
use App\Services\Logistics\VehicleRoutingProfileFactory;
use Illuminate\Support\Facades\Cache;

final class MatrixRoutePreviewService
{
    public function __construct(
        private readonly RoutingProviderInterface $provider,
        private readonly VehicleRoutingProfileFactory $profiles,
        private readonly GeoJsonFactory $geoJson,
        private readonly GisReleaseMetadataService $metadata,
    ) {}

    /** @return array<string, mixed> */
    public function preview(LogisticsCityDistance $distance): array
    {
        $distance->loadMissing(['fromCity:id,name', 'toCity:id,name']);
        $points = [
            new RoutingPoint(
                (float) $distance->from_latitude_snapshot,
                (float) $distance->from_longitude_snapshot,
                $distance->fromCity?->name,
            ),
            new RoutingPoint(
                (float) $distance->to_latitude_snapshot,
                (float) $distance->to_longitude_snapshot,
                $distance->toCity?->name,
            ),
        ];
        $pointFeatures = [
            $this->geoJson->point($points[0]->longitude, $points[0]->latitude, [
                'kind' => 'matrix_endpoint',
                'role' => 'from',
                'sequence' => 1,
                'sequence_label' => 'A',
                'city_id' => $distance->from_city_id,
                'city' => $distance->fromCity?->name,
            ], 'matrix-from-'.$distance->id),
            $this->geoJson->point($points[1]->longitude, $points[1]->latitude, [
                'kind' => 'matrix_endpoint',
                'role' => 'to',
                'sequence' => 2,
                'sequence_label' => 'B',
                'city_id' => $distance->to_city_id,
                'city' => $distance->toCity?->name,
            ], 'matrix-to-'.$distance->id),
        ];

        $base = [
            'distance' => [
                'id' => $distance->id,
                'from_city' => ['id' => $distance->from_city_id, 'name' => $distance->fromCity?->name],
                'to_city' => ['id' => $distance->to_city_id, 'name' => $distance->toCity?->name],
                'status' => $distance->status?->value,
                'distance_m' => $distance->distance_m,
                'duration_s' => $distance->duration_s,
                'routing_profile' => $distance->routing_profile,
                'provider' => $distance->provider,
                'routing_engine_version' => $distance->routing_engine_version,
                'osm_data_version' => $distance->osm_data_version,
                'calculated_at' => $distance->calculated_at?->toISOString(),
                'manual_note' => $distance->manual_note,
                'error_code' => $distance->error_code,
                'error_message' => $distance->error_message,
            ],
            'points' => $this->geoJson->featureCollection($pointFeatures),
            'route_feature' => null,
            'preview' => null,
        ];

        if ($distance->status === DistanceStatus::Manual) {
            return [
                ...$base,
                'message' => 'Ручное значение не содержит дорожной геометрии. Показаны только точки A и B.',
            ];
        }

        if (! in_array($distance->status, [DistanceStatus::Calculated, DistanceStatus::Stale], true)) {
            return [
                ...$base,
                'message' => match ($distance->status) {
                    DistanceStatus::NoRoute => 'Valhalla не нашла автомобильный маршрут для этой пары.',
                    DistanceStatus::Failed => 'Расчёт пары завершился ошибкой; геометрия недоступна.',
                    DistanceStatus::Pending => 'Пара ещё ожидает расчёта; геометрия недоступна.',
                    default => 'Геометрия для этой пары недоступна.',
                },
            ];
        }

        $profile = $this->profiles->make(null, $distance->routing_profile);
        $requestHash = RoutingHash::make([
            'kind' => 'matrix_preview',
            'distance_id' => $distance->id,
            'points' => array_map(fn (RoutingPoint $point): array => $point->toArray(), $points),
            'profile' => $profile->toArray(),
            'osm_data_version' => $this->metadata->osmDataVersion(),
            'distance_updated_at' => $distance->updated_at?->getTimestamp(),
        ]);
        $ttl = max(60, (int) config('logistics.map.matrix_preview_ttl', 21600));
        $cacheKey = 'logistics:matrix-route-preview:'.$requestHash;

        try {
            $preview = Cache::remember($cacheKey, $ttl, function () use ($points, $profile, $requestHash): array {
                $result = $this->provider->route(new RouteRequest($points, $profile, $requestHash));

                return [
                    'distance_m' => $result->distanceM,
                    'duration_s' => $result->durationS,
                    'shape_polyline6' => $result->shapePolyline6,
                    'provider' => $result->provider,
                    'routing_engine_version' => $result->routingEngineVersion,
                    'osm_data_version' => $result->osmDataVersion,
                ];
            });
        } catch (NoRouteException) {
            return [
                ...$base,
                'preview' => ['status' => 'no_route', 'cached' => false],
                'message' => 'Valhalla не нашла автомобильный маршрут для preview этой пары.',
            ];
        }

        $feature = filled($preview['shape_polyline6'] ?? null)
            ? $this->geoJson->lineStringFromPolyline6($preview['shape_polyline6'], [
                'kind' => 'matrix_route_preview',
                'distance_id' => $distance->id,
                'status' => $distance->status?->value,
            ], 'matrix-route-'.$distance->id)
            : null;

        return [
            ...$base,
            'route_feature' => $feature,
            'preview' => [
                'status' => $feature ? 'calculated' : 'geometry_missing',
                'distance_m' => $preview['distance_m'],
                'duration_s' => $preview['duration_s'],
                'provider' => $preview['provider'],
                'routing_engine_version' => $preview['routing_engine_version'],
                'osm_data_version' => $preview['osm_data_version'],
            ],
            'message' => match (true) {
                $feature === null => 'Routing-сервис не вернул дорожную геометрию; показаны только точки A и B.',
                $distance->status === DistanceStatus::Stale => 'Сохранённая пара помечена stale. Линия получена отдельным точечным preview и не изменяет значение матрицы.',
                default => null,
            },
        ];
    }
}
