<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripRoute;
use App\Services\Logistics\Map\GeoJsonFactory;
use Illuminate\Http\JsonResponse;
use Throwable;

class TripMapController extends Controller
{
    public function __construct(private readonly GeoJsonFactory $geoJson) {}

    public function current(LogisticsTrip $trip): JsonResponse
    {
        $this->authorizeLogistics('view', $trip);
        $trip->load(['stops.city.region', 'currentRoute']);

        return response()->json(['data' => $this->mapData($trip, $trip->currentRoute)]);
    }

    public function version(LogisticsTrip $trip, LogisticsTripRoute $route): JsonResponse
    {
        $this->authorizeLogistics('view', $trip);
        abort_unless((int) $route->trip_id === (int) $trip->id, 404);
        $trip->load('stops.city.region');

        return response()->json(['data' => $this->mapData($trip, $route)]);
    }

    /** @return array<string, mixed> */
    private function mapData(LogisticsTrip $trip, ?LogisticsTripRoute $route): array
    {
        $missing = [];
        $stopFeatures = [];
        foreach ($trip->stops as $stop) {
            if ($stop->latitude === null || $stop->longitude === null) {
                $missing[] = [
                    'id' => $stop->id,
                    'sequence' => $stop->sequence,
                    'city' => $stop->city?->name,
                ];

                continue;
            }

            $stopFeatures[] = $this->geoJson->point(
                (float) $stop->longitude,
                (float) $stop->latitude,
                [
                    'kind' => 'trip_stop',
                    'trip_id' => $trip->id,
                    'sequence' => $stop->sequence,
                    'sequence_label' => (string) $stop->sequence,
                    'city' => $stop->city?->name,
                    'region' => $stop->city?->region?->name,
                    'address' => $stop->address,
                    'stop_type' => $stop->stop_type?->value,
                    'operation_type' => $stop->operation_type?->value,
                    'planned_arrival_at' => $stop->planned_arrival_at?->toISOString(),
                    'planned_departure_at' => $stop->planned_departure_at?->toISOString(),
                    'actual_arrival_at' => $stop->actual_arrival_at?->toISOString(),
                    'actual_departure_at' => $stop->actual_departure_at?->toISOString(),
                ],
                'stop-'.$stop->id,
            );
        }

        $routeFeature = null;
        $geometryError = null;
        if (filled($route?->shape_polyline6)) {
            try {
                $routeFeature = $this->geoJson->lineStringFromPolyline6(
                    $route->shape_polyline6,
                    [
                        'kind' => 'trip_route',
                        'trip_id' => $trip->id,
                        'route_id' => $route->id,
                        'is_current' => $route->is_current,
                        'status' => $route->status?->value,
                    ],
                    'route-'.$route->id,
                );
            } catch (Throwable $exception) {
                report($exception);
                $geometryError = 'Сохранённая геометрия маршрута повреждена и не может быть показана.';
            }
        }

        $message = match (true) {
            $geometryError !== null => $geometryError,
            $route === null => 'Маршрут ещё не рассчитывался: показаны только остановки с координатами.',
            $routeFeature === null => 'Для этой версии нет сохранённой дорожной геометрии: показаны только остановки.',
            default => null,
        };

        return [
            'trip' => [
                'id' => $trip->id,
                'number' => $trip->number,
                'status' => $trip->status?->value,
            ],
            'route' => $route ? [
                'id' => $route->id,
                'is_current' => $route->is_current,
                'status' => $route->status?->value,
                'routing_profile' => $route->routing_profile,
                'distance_m' => $route->distance_m,
                'duration_s' => $route->duration_s,
                'provider' => $route->provider,
                'routing_engine_version' => $route->routing_engine_version,
                'osm_data_version' => $route->osm_data_version,
                'calculated_at' => $route->calculated_at?->toISOString(),
                'geometry_available' => $routeFeature !== null,
            ] : null,
            'route_feature' => $routeFeature,
            'stops' => $this->geoJson->featureCollection($stopFeatures),
            'missing_stop_coordinates' => $missing,
            'notice' => $route !== null && ! $route->is_current
                ? 'Показана сохранённая историческая линия; маркеры остановок отражают текущий сохранённый порядок рейса.'
                : null,
            'message' => $message,
        ];
    }
}
