<?php

namespace Tests\Unit;

use App\Services\Logistics\Routing\DTO\MatrixRequest;
use App\Services\Logistics\Routing\DTO\RouteRequest;
use App\Services\Logistics\Routing\DTO\RoutingPoint;
use App\Services\Logistics\Routing\DTO\VehicleRoutingProfile;
use App\Services\Logistics\Routing\Exceptions\MalformedRoutingResponseException;
use App\Services\Logistics\Routing\Exceptions\NoRouteException;
use App\Services\Logistics\Routing\Exceptions\ProviderUnavailableException;
use App\Services\Logistics\Routing\Providers\ValhallaRoutingProvider;
use App\Services\Logistics\Routing\Support\Polyline6;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ValhallaRoutingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'logistics.valhalla.base_url' => 'http://valhalla.test:8002',
            'logistics.valhalla.engine_version' => '3.6.3',
            'logistics.valhalla.retry_times' => 1,
            'logistics.valhalla.retry_delay_ms' => 0,
            'logistics.osm_data_version' => '260725',
        ]);
    }

    public function test_route_response_is_strictly_normalized_to_meters_seconds_and_polyline6(): void
    {
        $shape = Polyline6::encode([[59.9343, 30.3351], [55.7558, 37.6173]]);
        Http::fake([
            'http://valhalla.test:8002/route' => Http::response([
                'trip' => [
                    'summary' => ['length' => 712.345, 'time' => 35_999.6],
                    'legs' => [['shape' => $shape]],
                ],
            ]),
        ]);

        $result = (new ValhallaRoutingProvider)->route($this->routeRequest());

        $this->assertSame(712_345, $result->distanceM);
        $this->assertSame(36_000, $result->durationS);
        $this->assertSame($shape, $result->shapePolyline6);
        $this->assertSame('3.6.3', $result->routingEngineVersion);
        $this->assertSame('260725', $result->osmDataVersion);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'http://valhalla.test:8002/route'
                && $payload['costing'] === 'truck'
                && $payload['costing_options']['truck']['weight'] === 32.0
                && $payload['locations'][0]['type'] === 'break';
        });
    }

    public function test_matrix_response_normalizes_each_direction_without_symmetry_assumptions(): void
    {
        Http::fake([
            'http://valhalla.test:8002/sources_to_targets' => Http::response([
                'sources_to_targets' => [
                    'distances' => [[100.125, null], [98.5, 0]],
                    'durations' => [[7200.4, null], [7000, 0]],
                ],
            ]),
        ]);
        $points = [new RoutingPoint(59.9343, 30.3351), new RoutingPoint(55.7558, 37.6173)];
        $request = new MatrixRequest($points, array_reverse($points), $this->profile(), 'matrix-hash');

        $result = (new ValhallaRoutingProvider)->matrix($request);

        $this->assertSame(100_125, $result->cell(0, 0)->distanceM);
        $this->assertSame(7_200, $result->cell(0, 0)->durationS);
        $this->assertNull($result->cell(0, 1)->distanceM);
        $this->assertSame(98_500, $result->cell(1, 0)->distanceM);
        $this->assertSame(0, $result->cell(1, 1)->distanceM);
    }

    public function test_temporary_server_error_is_retried_with_a_bound(): void
    {
        $shape = Polyline6::encode([[59.9343, 30.3351], [55.7558, 37.6173]]);
        Http::fakeSequence()
            ->pushStatus(503)
            ->push(['trip' => ['summary' => ['length' => 700, 'time' => 36_000], 'legs' => [['shape' => $shape]]]]);

        (new ValhallaRoutingProvider)->route($this->routeRequest());

        Http::assertSentCount(2);
    }

    public function test_no_route_is_a_non_retryable_domain_error(): void
    {
        Http::fake([
            '*' => Http::response(['error_code' => 170, 'error' => 'No path could be found'], 400),
        ]);

        try {
            (new ValhallaRoutingProvider)->route($this->routeRequest());
            $this->fail('NoRouteException was not thrown.');
        } catch (NoRouteException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame('no_route', $exception->domainCode);
        }

        Http::assertSentCount(1);
    }

    public function test_connection_timeout_becomes_safe_provider_unavailable_error(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('socket timeout');
        });

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessage('Внутренний routing-сервис временно недоступен.');
        (new ValhallaRoutingProvider)->route($this->routeRequest());
    }

    public function test_malformed_success_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['trip' => ['summary' => ['length' => 10]]])]);

        $this->expectException(MalformedRoutingResponseException::class);
        (new ValhallaRoutingProvider)->route($this->routeRequest());
    }

    private function routeRequest(): RouteRequest
    {
        return new RouteRequest([
            new RoutingPoint(59.9343, 30.3351, 'Санкт-Петербург'),
            new RoutingPoint(55.7558, 37.6173, 'Москва'),
        ], $this->profile(), 'route-hash');
    }

    private function profile(): VehicleRoutingProfile
    {
        return new VehicleRoutingProfile('truck', [
            'height' => 4.0,
            'width' => 2.55,
            'length' => 16.5,
            'weight' => 32.0,
            'axle_load' => 10.0,
            'axle_count' => 5,
        ], 'profile-hash');
    }
}
