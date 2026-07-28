<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\Logistics\Routing\Support\Polyline6;
use App\Services\Logistics\Routing\Support\RoutingHash;
use App\Services\Logistics\VehicleRoutingProfileFactory;
use Tests\TestCase;

class LogisticsRoutingSupportTest extends TestCase
{
    public function test_truck_profile_uses_full_weight_and_metric_dimensions(): void
    {
        $vehicle = new Vehicle([
            'payload_capacity_kg' => 20_000,
            'gross_weight_kg' => 32_000,
            'height_m' => 4.0,
            'width_m' => 2.55,
            'length_m' => 16.5,
            'axle_count' => 5,
            'max_axle_load_t' => 10.5,
        ]);
        $profile = (new VehicleRoutingProfileFactory)->make($vehicle, 'truck');

        $this->assertSame(32.0, $profile->options['weight']);
        $this->assertSame(4.0, $profile->options['height']);
        $this->assertSame(2.55, $profile->options['width']);
        $this->assertSame(16.5, $profile->options['length']);
        $this->assertSame(10.5, $profile->options['axle_load']);
        $this->assertSame(5, $profile->options['axle_count']);
        $this->assertNotSame('default', $profile->hash);
        $this->assertSame([], (new VehicleRoutingProfileFactory)->make($vehicle, 'auto')->options);
    }

    public function test_hash_is_canonical_but_keeps_ordered_route_points_order_sensitive(): void
    {
        $this->assertSame(
            RoutingHash::make(['profile' => ['width' => 2.5, 'height' => 4], 'provider' => 'fake']),
            RoutingHash::make(['provider' => 'fake', 'profile' => ['height' => 4, 'width' => 2.5]])
        );
        $this->assertNotSame(
            RoutingHash::make(['points' => [[1, 2], [3, 4]]]),
            RoutingHash::make(['points' => [[3, 4], [1, 2]]])
        );
    }

    public function test_polyline6_round_trip_and_leg_combination_are_stable(): void
    {
        $first = Polyline6::encode([[59.9343, 30.3351], [57.8193, 28.3318]]);
        $second = Polyline6::encode([[57.8193, 28.3318], [55.7558, 37.6173]]);
        $combined = Polyline6::combine([$first, $second]);

        $this->assertSame([
            [59.9343, 30.3351],
            [57.8193, 28.3318],
            [55.7558, 37.6173],
        ], Polyline6::decode($combined));
    }
}
