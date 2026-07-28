<?php

namespace App\Services\Logistics;

use App\Enums\Logistics\ActualDistanceSource;
use App\Enums\Logistics\StopType;
use App\Models\LogisticsCity;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripStop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripWriterService
{
    public function create(array $payload, ?int $userId): LogisticsTrip
    {
        return DB::transaction(function () use ($payload, $userId) {
            $stops = Arr::pull($payload, 'stops', []);
            unset($payload['acknowledge_vehicle_warning']);

            $payload = $this->withActualDistance($payload);
            $trip = LogisticsTrip::query()->create([
                ...$payload,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncStops($trip, $stops);

            return $this->load($trip->refresh());
        }, 3);
    }

    public function update(LogisticsTrip $trip, array $payload, ?int $userId): LogisticsTrip
    {
        return DB::transaction(function () use ($trip, $payload, $userId) {
            $trip = LogisticsTrip::query()->withTrashed()->lockForUpdate()->findOrFail($trip->id);
            $hasStops = array_key_exists('stops', $payload);
            $stops = Arr::pull($payload, 'stops', []);
            unset($payload['acknowledge_vehicle_warning']);

            $payload = $this->withActualDistance($payload, $trip);
            $trip->fill([...$payload, 'updated_by' => $userId])->save();

            if ($hasStops) {
                $this->syncStops($trip, $stops);
            }

            return $this->load($trip->refresh());
        }, 3);
    }

    public function moveStop(LogisticsTrip $trip, LogisticsTripStop $stop, string $direction): LogisticsTrip
    {
        if ($stop->trip_id !== $trip->id) {
            abort(404);
        }

        return DB::transaction(function () use ($trip, $stop, $direction) {
            $trip = LogisticsTrip::query()->lockForUpdate()->findOrFail($trip->id);
            $stops = LogisticsTripStop::query()
                ->where('trip_id', $trip->id)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            $index = $stops->search(fn (LogisticsTripStop $item) => $item->id === $stop->id);
            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $stops->has($targetIndex)) {
                return $this->load($trip);
            }

            $current = $stops[$index];
            $target = $stops[$targetIndex];
            $currentSequence = $current->sequence;
            $targetSequence = $target->sequence;

            LogisticsTripStop::withoutEvents(function () use ($current, $target, $currentSequence, $targetSequence): void {
                $current->update(['sequence' => 65535]);
                $target->update(['sequence' => $currentSequence]);
                $current->update(['sequence' => $targetSequence]);
            });

            $this->normalizeStopTypes($trip);
            $trip->markRouteStale();

            return $this->load($trip->refresh());
        }, 3);
    }

    public function load(LogisticsTrip $trip): LogisticsTrip
    {
        return $trip->load([
            'vehicle.owner',
            'carrier',
            'responsible',
            'stops.city.region',
            'expenses.category',
            'expenses.check.entity',
            'currentRoute',
        ])->loadCount(['stops', 'expenses']);
    }

    private function syncStops(LogisticsTrip $trip, array $stops): void
    {
        if (count($stops) < 2) {
            throw ValidationException::withMessages([
                'stops' => 'Для рейса нужны минимум две остановки.',
            ]);
        }

        $cityIds = collect($stops)->pluck('city_id')->map(fn ($id) => (int) $id)->unique();
        $logisticsCities = LogisticsCity::query()
            ->whereIn('city_id', $cityIds)
            ->get()
            ->keyBy('city_id');

        LogisticsTripStop::withoutEvents(function () use ($trip, $stops, $logisticsCities): void {
            $trip->stops()->delete();

            foreach (array_values($stops) as $index => $stopPayload) {
                $point = $logisticsCities->get((int) $stopPayload['city_id']);
                $hasAddressPoint = ($stopPayload['latitude'] ?? null) !== null
                    && ($stopPayload['longitude'] ?? null) !== null;
                $hasVerifiedCityPoint = $point?->coordinates_verified_at && $point->hasRoutingPoint();

                $trip->stops()->create([
                    ...Arr::except($stopPayload, ['stop_type', 'sequence']),
                    'sequence' => $index + 1,
                    'stop_type' => $this->stopType($index, count($stops)),
                    'latitude' => $hasAddressPoint
                        ? $stopPayload['latitude']
                        : ($hasVerifiedCityPoint ? $point->routing_latitude : null),
                    'longitude' => $hasAddressPoint
                        ? $stopPayload['longitude']
                        : ($hasVerifiedCityPoint ? $point->routing_longitude : null),
                ]);
            }
        });

        $trip->markRouteStale();
    }

    private function normalizeStopTypes(LogisticsTrip $trip): void
    {
        $stops = $trip->stops()->get()->values();

        LogisticsTripStop::withoutEvents(function () use ($stops): void {
            foreach ($stops as $index => $stop) {
                $stop->update(['stop_type' => $this->stopType($index, $stops->count())]);
            }
        });
    }

    private function stopType(int $index, int $count): string
    {
        return match (true) {
            $index === 0 => StopType::Origin->value,
            $index === $count - 1 => StopType::Destination->value,
            default => StopType::Waypoint->value,
        };
    }

    private function withActualDistance(array $payload, ?LogisticsTrip $trip = null): array
    {
        $start = array_key_exists('odometer_start_km', $payload)
            ? $payload['odometer_start_km']
            : $trip?->odometer_start_km;
        $end = array_key_exists('odometer_end_km', $payload)
            ? $payload['odometer_end_km']
            : $trip?->odometer_end_km;

        if ($start !== null && $end !== null) {
            $payload['actual_distance_m'] = (int) round(((float) $end - (float) $start) * 1000);
            $payload['actual_distance_source'] = ActualDistanceSource::Odometer->value;
        } elseif (array_key_exists('actual_distance_m', $payload) && $payload['actual_distance_m'] !== null) {
            $payload['actual_distance_source'] ??= ActualDistanceSource::Manual->value;
        }

        return $payload;
    }
}
