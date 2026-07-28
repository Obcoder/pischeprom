<?php

namespace Tests\Feature\Logistics;

use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RoutingPoint;
use App\Services\Logistics\Routing\DTO\VehicleRoutingProfile;
use App\Services\Logistics\Routing\Providers\ValhallaRoutingProvider;
use Tests\TestCase;

class ValhallaSmokeTest extends TestCase
{
    public function test_real_container_can_route_saint_petersburg_to_moscow_when_explicitly_enabled(): void
    {
        if (! filter_var(env('RUN_VALHALLA_SMOKE', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Set RUN_VALHALLA_SMOKE=true to call a real private Valhalla instance.');
        }

        config([
            'logistics.valhalla.base_url' => env('VALHALLA_BASE_URL', 'http://127.0.0.1:8002'),
            'logistics.valhalla.retry_times' => 0,
        ]);
        $provider = new ValhallaRoutingProvider;
        $this->assertTrue($provider->health()->healthy);

        $result = $provider->route(new RouteRequest([
            new RoutingPoint(59.9343, 30.3351, 'Санкт-Петербург'),
            new RoutingPoint(55.7558, 37.6173, 'Москва'),
        ], new VehicleRoutingProfile('truck', ['height' => 4.0, 'weight' => 32.0], 'smoke-truck'), 'smoke-route'));

        $this->assertGreaterThan(0, $result->distanceM);
        $this->assertGreaterThan(0, $result->durationS);
        $this->assertSame('valhalla', $result->provider);
    }
}
