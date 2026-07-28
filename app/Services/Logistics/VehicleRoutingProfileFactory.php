<?php

namespace App\Services\Logistics;

use App\Models\Vehicle;
use App\Services\Logistics\Routing\DTO\VehicleRoutingProfile;
use App\Services\Logistics\Routing\Support\RoutingHash;

class VehicleRoutingProfileFactory
{
    public function make(?Vehicle $vehicle, ?string $routingProfile = null): VehicleRoutingProfile
    {
        $costing = $routingProfile ?: config('logistics.default_routing_profile', 'truck');
        $costing = $costing === 'auto' ? 'auto' : 'truck';

        if (! $vehicle) {
            return new VehicleRoutingProfile($costing, [], 'default');
        }

        $options = $costing === 'truck' ? array_filter([
            'height' => $this->float($vehicle->height_m),
            'width' => $this->float($vehicle->width_m),
            'length' => $this->float($vehicle->length_m),
            'weight' => $vehicle->gross_weight_kg !== null
                ? round((float) $vehicle->gross_weight_kg / 1000, 3)
                : null,
            'axle_load' => $this->float($vehicle->max_axle_load_t),
            'axle_count' => $vehicle->axle_count !== null ? (int) $vehicle->axle_count : null,
        ], fn ($value) => $value !== null) : [];

        $hash = RoutingHash::make([
            'costing' => $costing,
            'options' => $options,
        ]);

        return new VehicleRoutingProfile($costing, $options, $hash);
    }

    private function float(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 3);
    }
}
