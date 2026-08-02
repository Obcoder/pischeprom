<?php

namespace App\Services\Logistics;

use App\Enums\Logistics\RouteStatus;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripRoute;
use App\Models\LogisticsTripStop;
use App\Services\Logistics\Map\GisReleaseMetadataService;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RoutingPoint;
use App\Services\Logistics\Routing\Exceptions\NoRouteException;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use App\Services\Logistics\Routing\Support\RoutingHash;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripRouteService
{
    public function __construct(
        private readonly RoutingProviderInterface $provider,
        private readonly VehicleRoutingProfileFactory $profiles,
        private readonly GisReleaseMetadataService $releaseMetadata,
    ) {}

    public function calculate(LogisticsTrip $trip, ?int $createdBy = null, bool $force = false): LogisticsTripRoute
    {
        [$trip, $points] = $this->tripAndPoints($trip);
        $profile = $this->profiles->make($trip->vehicle, $trip->routing_profile);
        $requestHash = RoutingHash::make([
            'points' => array_map(fn (RoutingPoint $point) => $point->toArray(), $points),
            'profile' => $profile->toArray(),
            'provider' => $this->provider->code(),
            'osm_data_version' => $this->releaseMetadata->osmDataVersion(),
        ]);

        $request = new RouteRequest($points, $profile, $requestHash);
        $cache = config('logistics.lock_store')
            ? Cache::store(config('logistics.lock_store'))
            : Cache::getFacadeRoot();

        try {
            return $cache->lock("logistics:trip-route:{$trip->id}:{$requestHash}", 180)
                ->block(5, function () use ($trip, $request, $createdBy, $force) {
                    $existing = LogisticsTripRoute::query()
                        ->where('trip_id', $trip->id)
                        ->where('is_current', true)
                        ->where('request_hash', $request->requestHash)
                        ->where('status', RouteStatus::Calculated->value)
                        ->first();

                    if ($existing && ! $force) {
                        return $existing;
                    }

                    try {
                        $result = $this->provider->route($request);
                    } catch (RoutingException $exception) {
                        $this->persistFailure($trip, $request, $exception, $createdBy);
                        throw $exception;
                    }

                    return DB::transaction(function () use ($trip, $request, $result, $createdBy) {
                        $lockedTrip = LogisticsTrip::query()->lockForUpdate()->findOrFail($trip->id);
                        LogisticsTripRoute::query()->where('trip_id', $trip->id)->update(['is_current' => false]);

                        $route = LogisticsTripRoute::query()->create([
                            'trip_id' => $trip->id,
                            'is_current' => true,
                            'status' => RouteStatus::Calculated,
                            'routing_profile' => $request->profile->costing,
                            'vehicle_profile_hash' => $request->profile->hash,
                            'request_hash' => $request->requestHash,
                            'distance_m' => $result->distanceM,
                            'duration_s' => $result->durationS,
                            'shape_polyline6' => $result->shapePolyline6,
                            'legs' => $result->legs,
                            'routing_options' => $request->profile->options,
                            'provider' => $result->provider,
                            'routing_engine_version' => $result->routingEngineVersion,
                            'osm_data_version' => $result->osmDataVersion,
                            'calculated_at' => now(),
                            'created_by' => $createdBy,
                        ]);

                        $lockedTrip->forceFill([
                            'planned_distance_m' => $result->distanceM,
                            'planned_duration_s' => $result->durationS,
                            'routing_profile_hash' => $request->profile->hash,
                            'route_calculated_at' => now(),
                        ])->saveQuietly();

                        return $route;
                    }, 3);
                });
        } catch (LockTimeoutException $exception) {
            throw new RoutingException(
                'Расчёт этого маршрута уже выполняется.',
                'route_locked',
                true,
                409,
                $exception,
            );
        }
    }

    private function tripAndPoints(LogisticsTrip $trip): array
    {
        return DB::transaction(function () use ($trip) {
            $trip = LogisticsTrip::query()
                ->with(['vehicle', 'stops.city.logisticsSetting'])
                ->lockForUpdate()
                ->findOrFail($trip->id);

            if ($trip->stops->count() < 2) {
                throw ValidationException::withMessages(['stops' => 'Для расчёта маршрута нужны минимум две остановки.']);
            }

            $points = [];
            foreach ($trip->stops as $stop) {
                $latitude = $stop->latitude;
                $longitude = $stop->longitude;
                $setting = $stop->city?->logisticsSetting;

                if (($latitude === null || $longitude === null)
                    && $setting?->coordinates_verified_at && $setting->hasRoutingPoint()) {
                    $latitude = $setting->routing_latitude;
                    $longitude = $setting->routing_longitude;
                    LogisticsTripStop::withoutEvents(fn () => $stop->update([
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]));
                }

                if ($latitude === null || $longitude === null) {
                    throw ValidationException::withMessages([
                        'stops' => "У остановки «{$stop->city?->name}» нет проверенной routing-точки или адресных координат.",
                    ]);
                }

                $points[] = new RoutingPoint(
                    (float) $latitude,
                    (float) $longitude,
                    $stop->city?->name,
                );
            }

            return [$trip, $points];
        }, 3);
    }

    private function persistFailure(
        LogisticsTrip $trip,
        RouteRequest $request,
        RoutingException $exception,
        ?int $createdBy,
    ): void {
        DB::transaction(function () use ($trip, $request, $exception, $createdBy): void {
            $lockedTrip = LogisticsTrip::query()->lockForUpdate()->findOrFail($trip->id);
            LogisticsTripRoute::query()->where('trip_id', $trip->id)->update(['is_current' => false]);

            $status = $exception instanceof NoRouteException ? RouteStatus::NoRoute : RouteStatus::Failed;
            $route = LogisticsTripRoute::query()
                ->where('trip_id', $trip->id)
                ->where('request_hash', $request->requestHash)
                ->where('status', $status->value)
                ->latest('id')
                ->first();

            $values = [
                'is_current' => true,
                'status' => $status,
                'routing_profile' => $request->profile->costing,
                'vehicle_profile_hash' => $request->profile->hash,
                'routing_options' => [
                    ...$request->profile->options,
                    'error_code' => $exception->domainCode,
                    'error_message' => $exception->getMessage(),
                ],
                'provider' => $this->provider->code(),
                'routing_engine_version' => config('logistics.valhalla.engine_version'),
                'osm_data_version' => $this->releaseMetadata->osmDataVersion(),
                'calculated_at' => now(),
                'created_by' => $createdBy,
            ];

            if ($route) {
                $route->update($values);
            } else {
                LogisticsTripRoute::query()->create([
                    ...$values,
                    'trip_id' => $trip->id,
                    'request_hash' => $request->requestHash,
                ]);
            }

            $lockedTrip->forceFill([
                'routing_profile_hash' => $request->profile->hash,
                'route_calculated_at' => null,
            ])->saveQuietly();
        }, 3);
    }
}
