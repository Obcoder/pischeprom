<?php

namespace Tests\Feature\Logistics;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RouteStatus;
use App\Models\Entity;
use App\Models\EntityLocation;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripRoute;
use App\Models\Vehicle;
use App\Services\Logistics\Map\GisReleaseMetadataService;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\RouteResult;
use App\Services\Logistics\Routing\Providers\FakeRoutingProvider;
use App\Services\Logistics\Routing\Support\Polyline6;

class LogisticsMapTest extends LogisticsTestCase
{
    public function test_verified_activation_manifest_enables_map_without_exposing_manifest_paths(): void
    {
        $user = $this->logisticsUser(['logistics.view']);
        $directory = storage_path('framework/testing/logistics-gis-'.bin2hex(random_bytes(5)));
        mkdir($directory, 0700, true);
        $manifest = $directory.'/release.json';
        $activation = $directory.'/activation.json';
        file_put_contents($manifest, json_encode([
            'release' => 'russia-20260801',
            'status' => 'verified',
            'coverage' => 'Russia',
            'osm_data_version' => '20260801',
            'verified_at' => '2026-08-02T10:00:00Z',
            'pbf' => [
                'source_url' => 'https://download.geofabrik.de/russia-latest.osm.pbf',
                'size_bytes' => 4_134_132_440,
                'md5' => 'eaefeb62007ed1dc9e0a180dd3717d86',
            ],
            'pmtiles' => [
                'size_bytes' => 2_000_000_000,
                'sha256' => str_repeat('a', 64),
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($activation, json_encode([
            'release' => 'russia-20260801',
            'previous_release' => 'russia-20260731',
            'status' => 'active',
            'activated_at' => '2026-08-02T11:00:00Z',
            'production_smoke' => 'passed',
        ], JSON_THROW_ON_ERROR));
        config([
            'logistics.map.enabled' => true,
            'logistics.map.release_manifest_path' => $manifest,
            'logistics.map.activation_status_path' => $activation,
        ]);

        try {
            $response = $this->actingAs($user)->getJson('/api/logistics/map/config')
                ->assertOk()
                ->assertJsonPath('data.enabled', true)
                ->assertJsonPath('data.style_url', '/api/logistics/map/style?v=1-russia-20260801')
                ->assertJsonPath('data.pmtiles_url', '/maps/logistics/russia.pmtiles?v=1-russia-20260801')
                ->assertJsonPath('data.release.status', 'active')
                ->assertJsonPath('data.release.release', 'russia-20260801')
                ->assertJsonPath('data.release.updated_at', '2026-08-02T11:00:00Z');

            $this->assertStringNotContainsString($directory, $response->getContent());
            $this->assertSame('20260801', app(GisReleaseMetadataService::class)->osmDataVersion());

            file_put_contents($activation, json_encode([
                'release' => 'russia-20260731',
                'status' => 'active',
            ], JSON_THROW_ON_ERROR));
            $this->actingAs($user)->getJson('/api/logistics/map/config')
                ->assertOk()
                ->assertJsonPath('data.enabled', false)
                ->assertJsonPath('data.release.status', 'verified');
            $this->assertSame('test-osm-1', app(GisReleaseMetadataService::class)->osmDataVersion());
        } finally {
            @unlink($manifest);
            @unlink($activation);
            @rmdir($directory);
        }
    }

    public function test_map_configuration_and_bbox_layers_are_authorized_limited_and_safe(): void
    {
        $this->getJson('/api/logistics/map/config')->assertUnauthorized();

        $forbiddenUser = $this->logisticsUser(['logistics.trips.manage']);
        $this->actingAs($forbiddenUser)->getJson('/api/logistics/map/config')->assertForbidden();

        $user = $this->logisticsUser(['logistics.view']);
        $city = $this->verifiedCity('Москва', 55.7558, 37.6173, $user->id);
        $trip = LogisticsTrip::factory()->create(['vehicle_id' => Vehicle::factory()->create()->id]);
        $trip->stops()->create([
            'sequence' => 1,
            'city_id' => $city->id,
            'stop_type' => 'origin',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);
        $trip->stops()->create([
            'sequence' => 2,
            'city_id' => $city->id,
            'stop_type' => 'destination',
            'latitude' => 55.8,
            'longitude' => 37.7,
        ]);
        $route = $this->route($trip, true, Polyline6::encode([
            [55.7558, 37.6173],
            [55.8, 37.7],
        ]));
        $entity = Entity::query()->create(['name' => 'Безопасный контрагент']);
        EntityLocation::query()->create([
            'entity_id' => $entity->id,
            'address_text' => 'Москва, тестовый адрес',
            'lat' => 55.76,
            'lon' => 37.62,
            'source' => 'manual',
            'precision_level' => 'exact',
            'is_confirmed' => true,
        ]);

        $configuration = $this->actingAs($user)->getJson('/api/logistics/map/config')
            ->assertOk()
            ->assertJsonPath('data.coverage', 'Russia')
            ->assertJsonPath('data.style_url', '/api/logistics/map/style?v=1')
            ->assertJsonPath('data.pmtiles_url', '/maps/logistics/russia.pmtiles?v=1')
            ->assertJsonPath('data.entity_layer_available', true);
        $this->assertStringNotContainsString('/srv/', $configuration->getContent());

        $this->getJson('/api/logistics/map/style')
            ->assertOk()
            ->assertJsonPath('sources.logistics-basemap.url', 'pmtiles:///maps/logistics/russia.pmtiles?v=1')
            ->assertJsonPath('glyphs', '/maps/logistics/fonts/{fontstack}/{range}.pbf?v=1')
            ->assertJsonPath(
                'sources.logistics-basemap.attribution',
                '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a> · © <a href="https://openmaptiles.org/">OpenMapTiles</a>'
            );

        $this->getJson('/api/logistics/map/features?bbox=bad&zoom=2')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bbox');

        $response = $this->getJson('/api/logistics/map/features?bbox=20,40,50,70&zoom=6&layers[]=cities&layers[]=trips&layers[]=entities')
            ->assertOk()
            ->assertJsonPath('data.cities.type', 'FeatureCollection')
            ->assertJsonPath('data.cities.features.0.geometry.coordinates.0', 37.6173)
            ->assertJsonPath('data.cities.features.0.geometry.coordinates.1', 55.7558)
            ->assertJsonPath('data.entities.features.0.properties.name', 'Безопасный контрагент')
            ->assertJsonPath('data.trips.0.id', $trip->id)
            ->assertJsonPath('data.trips.0.current_route.id', $route->id)
            ->assertJsonPath('data.trips.0.current_route.geometry_available', true);

        $response->assertJsonMissingPath('data.trips.0.current_route.shape_polyline6');
        $response->assertJsonMissingPath('data.trips.0.current_route.legs');
    }

    public function test_current_and_historical_route_geometry_are_returned_without_changing_the_active_version(): void
    {
        $user = $this->logisticsUser(['logistics.view']);
        $from = $this->city('Псков', 57.8193, 28.3318);
        $to = $this->city('Санкт-Петербург', 59.9343, 30.3351);
        $trip = LogisticsTrip::factory()->create();
        $trip->stops()->create([
            'sequence' => 1,
            'city_id' => $from->id,
            'stop_type' => 'origin',
            'operation_type' => 'loading',
            'latitude' => 57.8193,
            'longitude' => 28.3318,
        ]);
        $trip->stops()->create([
            'sequence' => 2,
            'city_id' => $to->id,
            'stop_type' => 'destination',
            'operation_type' => 'unloading',
            'latitude' => 59.9343,
            'longitude' => 30.3351,
        ]);
        $historical = $this->route($trip, false, Polyline6::encode([
            [57.8193, 28.3318],
            [58.9, 29.4],
            [59.9343, 30.3351],
        ]), 'test-osm-old');
        $current = $this->route($trip, true, Polyline6::encode([
            [57.8193, 28.3318],
            [59.0, 29.8],
            [59.9343, 30.3351],
        ]), 'test-osm-current');

        $this->actingAs($user)->getJson("/api/logistics/trips/{$trip->id}/map")
            ->assertOk()
            ->assertJsonPath('data.route.id', $current->id)
            ->assertJsonPath('data.route.is_current', true)
            ->assertJsonPath('data.route_feature.geometry.type', 'LineString')
            ->assertJsonPath('data.route_feature.geometry.coordinates.0.0', 28.3318)
            ->assertJsonPath('data.route_feature.geometry.coordinates.0.1', 57.8193)
            ->assertJsonPath('data.stops.features.0.properties.sequence_label', '1')
            ->assertJsonPath('data.stops.features.1.properties.sequence_label', '2');

        $this->getJson("/api/logistics/trips/{$trip->id}/routes/{$historical->id}/map")
            ->assertOk()
            ->assertJsonPath('data.route.id', $historical->id)
            ->assertJsonPath('data.route.is_current', false)
            ->assertJsonPath('data.route_feature.properties.is_current', false);

        $this->assertTrue($current->refresh()->is_current);
        $this->assertFalse($historical->refresh()->is_current);

        $this->getJson("/api/logistics/trips/{$trip->id}/routes?summary=1")
            ->assertOk()
            ->assertJsonPath('data.0.geometry_available', true)
            ->assertJsonMissingPath('data.0.shape_polyline6')
            ->assertJsonMissingPath('data.0.legs');

        $this->getJson('/api/logistics/trips?summary=1')
            ->assertOk()
            ->assertJsonPath('data.0.current_route.geometry_available', true)
            ->assertJsonMissingPath('data.0.current_route.shape_polyline6')
            ->assertJsonMissingPath('data.0.current_route.legs');

        $this->getJson("/api/logistics/trips/{$trip->id}/routes")
            ->assertOk()
            ->assertJsonPath('data.0.shape_polyline6', $current->shape_polyline6)
            ->assertJsonPath('data.0.legs', []);
    }

    public function test_missing_route_geometry_and_manual_matrix_preview_remain_point_only(): void
    {
        $user = $this->logisticsUser(['logistics.view']);
        $from = $this->verifiedCity('Тверь', 56.8587, 35.9176, $user->id);
        $to = $this->verifiedCity('Ярославль', 57.6261, 39.8845, $user->id);
        $trip = LogisticsTrip::factory()->create();
        $trip->stops()->create([
            'sequence' => 1, 'city_id' => $from->id, 'stop_type' => 'origin',
            'latitude' => 56.8587, 'longitude' => 35.9176,
        ]);
        $trip->stops()->create([
            'sequence' => 2, 'city_id' => $to->id, 'stop_type' => 'destination',
            'latitude' => 57.6261, 'longitude' => 39.8845,
        ]);
        $this->route($trip, true, null);

        $this->actingAs($user)->getJson("/api/logistics/trips/{$trip->id}/map")
            ->assertOk()
            ->assertJsonPath('data.route_feature', null)
            ->assertJsonPath('data.route.geometry_available', false)
            ->assertJsonPath('data.stops.features.0.geometry.coordinates.0', 35.9176)
            ->assertJsonPath('data.message', 'Для этой версии нет сохранённой дорожной геометрии: показаны только остановки.');

        $manual = $this->distance($from->id, $to->id, DistanceStatus::Manual, 'truck');
        $provider = $this->fakeProvider();
        $callsBefore = $provider->routeCalls;

        $this->getJson("/api/logistics/matrix/{$manual->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.distance.status', 'manual')
            ->assertJsonPath('data.route_feature', null)
            ->assertJsonPath('data.points.features.0.geometry.coordinates.0', 35.9176)
            ->assertJsonPath('data.message', 'Ручное значение не содержит дорожной геометрии. Показаны только точки A и B.');

        $this->assertSame($callsBefore, $provider->routeCalls);
    }

    public function test_matrix_route_preview_is_calculated_on_demand_cached_and_does_not_create_pairs(): void
    {
        $user = $this->logisticsUser(['logistics.view']);
        $from = $this->verifiedCity('Москва preview', 55.7558, 37.6173, $user->id);
        $to = $this->verifiedCity('Нижний Новгород preview', 56.3269, 44.0060, $user->id);
        $distance = $this->distance($from->id, $to->id, DistanceStatus::Calculated, 'truck');
        $provider = $this->fakeProvider();
        $provider->routeResult = new RouteResult(
            distanceM: 422_000,
            durationS: 25_200,
            shapePolyline6: Polyline6::encode([
                [55.7558, 37.6173],
                [56.0, 41.0],
                [56.3269, 44.0060],
            ]),
            legs: [],
            provider: 'fake',
            routingEngineVersion: 'fake-1.0',
            osmDataVersion: 'fixture',
        );
        $pairCount = LogisticsCityDistance::query()->count();

        $this->actingAs($user)->getJson("/api/logistics/matrix/{$distance->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.preview.status', 'calculated')
            ->assertJsonPath('data.route_feature.geometry.type', 'LineString')
            ->assertJsonPath('data.route_feature.geometry.coordinates.2.0', 44.006)
            ->assertJsonPath('data.route_feature.geometry.coordinates.2.1', 56.3269);
        $this->getJson("/api/logistics/matrix/{$distance->id}/preview")->assertOk();
        $this->assertSame(1, $provider->routeCalls);

        $distance->update(['status' => DistanceStatus::Stale]);
        $this->getJson("/api/logistics/matrix/{$distance->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.distance.status', 'stale')
            ->assertJsonPath(
                'data.message',
                'Сохранённая пара помечена stale. Линия получена отдельным точечным preview и не изменяет значение матрицы.'
            );

        $this->assertSame($pairCount, LogisticsCityDistance::query()->count());
        $this->assertSame(DistanceStatus::Stale, $distance->refresh()->status);
    }

    public function test_diagnostics_keep_the_last_successful_healthcheck_when_routing_becomes_unavailable(): void
    {
        $user = $this->logisticsUser(['logistics.view', 'logistics.technical.view']);
        $provider = $this->fakeProvider();

        $healthy = $this->actingAs($user)->getJson('/api/logistics/routing-status')
            ->assertOk()
            ->assertJsonPath('data.healthy', true)
            ->assertJsonPath('data.overall_status', 'degraded')
            ->json('data');
        $this->assertSame($healthy['last_healthcheck_at'], $healthy['last_successful_healthcheck_at']);

        $this->travel(1)->minute();
        $provider->healthy = false;

        $unavailable = $this->getJson('/api/logistics/routing-status')
            ->assertServiceUnavailable()
            ->assertJsonPath('data.healthy', false)
            ->assertJsonPath('data.overall_status', 'unavailable')
            ->json('data');
        $this->assertSame($healthy['last_successful_healthcheck_at'], $unavailable['last_successful_healthcheck_at']);
        $this->assertNotSame($healthy['last_healthcheck_at'], $unavailable['last_healthcheck_at']);
    }

    private function verifiedCity(string $name, float $latitude, float $longitude, int $userId): \App\Models\City
    {
        $city = $this->city($name, $latitude, $longitude);
        LogisticsCity::query()->create([
            'city_id' => $city->id,
            'routing_latitude' => $latitude,
            'routing_longitude' => $longitude,
            'coordinate_source' => 'existing',
            'is_matrix_enabled' => true,
            'coordinates_verified_at' => now(),
            'coordinates_verified_by' => $userId,
        ]);

        return $city;
    }

    private function route(
        LogisticsTrip $trip,
        bool $current,
        ?string $shape,
        string $osmVersion = 'test-osm-1',
    ): LogisticsTripRoute {
        return $trip->routes()->create([
            'is_current' => $current,
            'status' => RouteStatus::Calculated,
            'routing_profile' => 'truck',
            'vehicle_profile_hash' => 'default',
            'request_hash' => hash('sha256', $trip->id.'|'.$current.'|'.$osmVersion),
            'distance_m' => 100_000,
            'duration_s' => 7_200,
            'shape_polyline6' => $shape,
            'legs' => [],
            'provider' => 'fake',
            'routing_engine_version' => 'fake-1.0',
            'osm_data_version' => $osmVersion,
            'calculated_at' => now(),
        ]);
    }

    private function distance(
        int $fromCityId,
        int $toCityId,
        DistanceStatus $status,
        string $profile,
    ): LogisticsCityDistance {
        $from = LogisticsCity::query()->where('city_id', $fromCityId)->sole();
        $to = LogisticsCity::query()->where('city_id', $toCityId)->sole();

        return LogisticsCityDistance::query()->create([
            'from_city_id' => $fromCityId,
            'to_city_id' => $toCityId,
            'routing_profile' => $profile,
            'vehicle_profile_hash' => 'default',
            'status' => $status,
            'distance_m' => 400_000,
            'duration_s' => 24_000,
            'from_latitude_snapshot' => $from->routing_latitude,
            'from_longitude_snapshot' => $from->routing_longitude,
            'to_latitude_snapshot' => $to->routing_latitude,
            'to_longitude_snapshot' => $to->routing_longitude,
            'provider' => $status === DistanceStatus::Manual ? 'manual' : 'fake',
            'routing_engine_version' => $status === DistanceStatus::Manual ? null : 'fake-1.0',
            'osm_data_version' => 'test-osm-1',
            'request_hash' => hash('sha256', $fromCityId.'|'.$toCityId.'|'.$profile.'|'.$status->value),
            'calculated_at' => now(),
            'manual_note' => $status === DistanceStatus::Manual ? 'Проверено диспетчером.' : null,
        ]);
    }

    private function fakeProvider(): FakeRoutingProvider
    {
        $provider = app(RoutingProviderInterface::class);
        $this->assertInstanceOf(FakeRoutingProvider::class, $provider);

        return $provider;
    }
}
