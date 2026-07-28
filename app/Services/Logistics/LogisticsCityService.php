<?php

namespace App\Services\Logistics;

use App\Enums\Logistics\CoordinateSource;
use App\Models\City;
use App\Models\LogisticsCity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsCityService
{
    public function upsert(City $city, array $payload, ?int $userId): LogisticsCity
    {
        return DB::transaction(function () use ($city, $payload, $userId) {
            $existing = LogisticsCity::query()->where('city_id', $city->id)->lockForUpdate()->first();
            $providedCoordinates = array_key_exists('routing_latitude', $payload)
                || array_key_exists('routing_longitude', $payload);

            if (! $existing && ! $providedCoordinates) {
                $payload['routing_latitude'] = $city->latitude;
                $payload['routing_longitude'] = $city->longitude;
                $payload['coordinate_source'] = CoordinateSource::Existing->value;
            }

            if (($payload['mark_verified'] ?? false) === true) {
                $latitude = $payload['routing_latitude'] ?? $existing?->routing_latitude;
                $longitude = $payload['routing_longitude'] ?? $existing?->routing_longitude;

                if ($latitude === null || $longitude === null) {
                    throw ValidationException::withMessages([
                        'routing_latitude' => 'Нельзя подтвердить точку маршрутизации без широты и долготы.',
                    ]);
                }

                $payload['coordinates_verified_at'] = now();
                $payload['coordinates_verified_by'] = $userId;
            } elseif ($providedCoordinates && $existing
                && ($this->coordinateChanged($existing->routing_latitude, $payload['routing_latitude'] ?? null)
                    || $this->coordinateChanged($existing->routing_longitude, $payload['routing_longitude'] ?? null))) {
                $payload['coordinates_verified_at'] = null;
                $payload['coordinates_verified_by'] = null;
            }

            unset($payload['mark_verified']);

            $setting = LogisticsCity::query()->updateOrCreate(
                ['city_id' => $city->id],
                $payload
            );

            return $setting->load('city.region', 'verifier');
        }, 3);
    }

    private function coordinateChanged(mixed $current, mixed $next): bool
    {
        if ($current === null || $next === null) {
            return $current !== null || $next !== null;
        }

        return abs((float) $current - (float) $next) > 0.00000005;
    }
}
