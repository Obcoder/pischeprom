<?php

namespace Tests\Feature\Logistics;

use App\Enums\Logistics\DistanceStatus;
use App\Enums\Logistics\RouteStatus;
use App\Enums\Logistics\RoutingRunStatus;
use App\Enums\Logistics\RoutingRunType;
use App\Jobs\Logistics\CalculateDistanceMatrixBatchJob;
use App\Jobs\Logistics\CalculateTripRouteJob;
use App\Models\LogisticsCity;
use App\Models\LogisticsCityDistance;
use App\Models\LogisticsRoutingRun;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripRoute;
use App\Models\Vehicle;
use App\Services\Logistics\CityDistanceMatrixService;
use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use App\Services\Logistics\Routing\DTO\MatrixResult;
use App\Services\Logistics\Routing\DTO\RouteResult;
use App\Services\Logistics\Routing\Exceptions\NoRouteException;
use App\Services\Logistics\Routing\Providers\FakeRoutingProvider;
use App\Services\Logistics\RoutingRunService;
use App\Services\Logistics\TripRouteService;
use Illuminate\Support\Facades\Queue;

class LogisticsRoutingTest extends LogisticsTestCase
{
    public function test_directed_matrix_is_calculated_and_manual_value_is_never_overwritten(): void
    {
        $user = $this->logisticsUser();
        $from = $this->verifiedCity('Санкт-Петербург', 59.9343, 30.3351, $user->id);
        $to = $this->verifiedCity('Москва', 55.7558, 37.6173, $user->id);
        $service = app(CityDistanceMatrixService::class);

        $manual = $service->setManual([
            'from_city_id' => $from->id,
            'to_city_id' => $to->id,
            'routing_profile' => 'truck',
            'distance_m' => 710_000,
            'duration_s' => 36_000,
            'manual_note' => 'Проверено диспетчером по фактическому рейсу.',
        ]);
        $run = $service->enqueue([$from->id, $to->id], 'truck', true, false, $user->id);

        $this->assertSame(DistanceStatus::Manual, $manual->refresh()->status);
        $this->assertSame(710_000, $manual->distance_m);
        $this->assertDatabaseHas('logistics_city_distances', [
            'from_city_id' => $to->id,
            'to_city_id' => $from->id,
            'status' => DistanceStatus::Calculated->value,
        ]);
        $this->assertSame(2, LogisticsCityDistance::query()->count());
        $this->assertSame(RoutingRunStatus::Completed, $run->refresh()->status);

        $from->logisticsSetting->update(['routing_latitude' => 59.935]);
        $this->assertSame(DistanceStatus::Manual, $manual->refresh()->status);
        $this->assertDatabaseHas('logistics_city_distances', [
            'from_city_id' => $to->id,
            'to_city_id' => $from->id,
            'status' => DistanceStatus::Stale->value,
        ]);
    }

    public function test_matrix_calculation_rejects_an_unverified_city_with_an_actionable_error(): void
    {
        $user = $this->logisticsUser();
        $ready = $this->verifiedCity('Москва-проверенная', 55.7558, 37.6173, $user->id);
        $unverified = $this->city('Воронеж-непроверенный', 51.6608, 39.2003);
        LogisticsCity::query()->create([
            'city_id' => $unverified->id,
            'routing_latitude' => 51.6608,
            'routing_longitude' => 39.2003,
            'coordinate_source' => 'existing',
            'is_matrix_enabled' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/logistics/matrix/calculate', [
                'city_ids' => [$ready->id, $unverified->id],
                'routing_profile' => 'truck',
                'refresh' => true,
                'missing_only' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city_ids')
            ->assertJsonPath(
                'errors.city_ids.0',
                'У города «Воронеж-непроверенный» не подтверждена точка маршрутизации. Укажите координаты на доступной автомобильной дороге и подтвердите их в настройках города.',
            );

        $this->assertDatabaseCount('logistics_routing_runs', 0);
    }

    public function test_matrix_persists_no_route_without_using_a_straight_line_fallback(): void
    {
        $user = $this->logisticsUser();
        $from = $this->verifiedCity('Псков', 57.8193, 28.3318, $user->id);
        $to = $this->verifiedCity('Новгород', 58.5228, 31.2699, $user->id);
        $provider = $this->fakeProvider();
        $provider->matrixResult = new MatrixResult([], 'fake', 'fake-1.0', 'fixture-no-route');

        app(CityDistanceMatrixService::class)->enqueue([$from->id, $to->id], 'truck', false, true, $user->id);

        $this->assertSame(1, $provider->matrixCalls);
        $this->assertDatabaseCount('logistics_city_distances', 2);
        $this->assertSame(2, LogisticsCityDistance::query()->where('status', DistanceStatus::NoRoute->value)->count());
        $this->assertSame(0, LogisticsCityDistance::query()->whereNotNull('distance_m')->count());
        $this->assertSame(
            RoutingRunStatus::Failed,
            LogisticsRoutingRun::query()->sole()->status,
        );
    }

    public function test_stale_refresh_command_is_bounded_and_dry_run_does_not_write(): void
    {
        $user = $this->logisticsUser();
        $from = $this->verifiedCity('Вологда', 59.2205, 39.8915, $user->id);
        $to = $this->verifiedCity('Ярославль', 57.6261, 39.8845, $user->id);
        $service = app(CityDistanceMatrixService::class);

        $service->enqueue([$from->id, $to->id], 'truck', false, true, $user->id);
        LogisticsCityDistance::query()->update(['status' => DistanceStatus::Stale->value]);

        $this->artisan('logistics:matrix-refresh-stale', [
            '--profile' => 'truck',
            '--limit' => 1,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(2, LogisticsCityDistance::query()->where('status', DistanceStatus::Stale->value)->count());
        $this->assertDatabaseCount('logistics_routing_runs', 1);

        $this->artisan('logistics:matrix-refresh-stale', [
            '--profile' => 'truck',
            '--limit' => 1,
        ])->assertSuccessful();

        $run = LogisticsRoutingRun::query()
            ->where('parameters->refresh_stale', true)
            ->sole();
        $this->assertSame(1, $run->total_pairs);
        $this->assertSame(RoutingRunStatus::Completed, $run->status);
        $this->assertSame(1, LogisticsCityDistance::query()->where('status', DistanceStatus::Stale->value)->count());
        $this->assertSame(1, LogisticsCityDistance::query()->where('status', DistanceStatus::Calculated->value)->count());
    }

    public function test_matrix_run_finishes_when_queued_coordinates_are_superseded(): void
    {
        Queue::fake();
        $user = $this->logisticsUser();
        $from = $this->verifiedCity('Архангельск', 64.5399, 40.5158, $user->id);
        $to = $this->verifiedCity('Вологда-2', 59.2205, 39.8915, $user->id);
        $service = app(CityDistanceMatrixService::class);
        $run = $service->enqueue([$from->id, $to->id], 'truck', false, true, $user->id);
        $queuedJob = null;

        Queue::assertPushed(CalculateDistanceMatrixBatchJob::class, function ($job) use (&$queuedJob): bool {
            $queuedJob = $job;

            return true;
        });

        $from->logisticsSetting->update(['routing_latitude' => 64.5401]);
        $queuedJob->handle($service, app(RoutingRunService::class));

        $run->refresh();
        $this->assertSame(RoutingRunStatus::Failed, $run->status);
        $this->assertSame(0, $run->completed_pairs);
        $this->assertSame(2, $run->failed_pairs);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(0, $this->fakeProvider()->matrixCalls);
    }

    public function test_full_matrix_bypasses_fragment_limit_and_uses_every_ready_city(): void
    {
        Queue::fake();
        config(['logistics.matrix_max_cities_per_request' => 2]);
        $user = $this->logisticsUser();
        $first = $this->verifiedCity('Полная-1', 59.9343, 30.3351, $user->id);
        $second = $this->verifiedCity('Полная-2', 56.8587, 35.9176, $user->id);
        $third = $this->verifiedCity('Полная-3', 55.7558, 37.6173, $user->id);
        $disabled = $this->verifiedCity('Полная-отключён', 57.8193, 28.3318, $user->id);
        $disabled->logisticsSetting->update(['is_matrix_enabled' => false]);

        $this->artisan('logistics:matrix-calculate', [
            '--all' => true,
            '--dry-run' => true,
        ])->expectsOutputToContain('3 cities, at most 6 directed pairs')
            ->assertSuccessful();
        $this->assertDatabaseCount('logistics_routing_runs', 0);

        $this->actingAs($user)->postJson('/api/logistics/matrix/calculate', [
            'city_ids' => [$first->id, $second->id, $third->id],
            'routing_profile' => 'truck',
        ])->assertUnprocessable()->assertJsonValidationErrors('city_ids');

        $response = $this->postJson('/api/logistics/matrix/calculate', [
            'full_matrix' => true,
            'routing_profile' => 'truck',
            'refresh' => false,
            'missing_only' => true,
        ])->assertAccepted()
            ->assertJsonPath('data.total_pairs', 6)
            ->assertJsonPath('data.parameters.full_matrix', true);

        $run = LogisticsRoutingRun::query()->findOrFail($response->json('data.id'));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id, $third->id],
            $run->parameters['city_ids'],
        );
        $this->assertDatabaseCount('logistics_city_distances', 6);
        Queue::assertPushed(CalculateDistanceMatrixBatchJob::class);
    }

    public function test_trip_route_is_idempotent_and_forced_recalculation_keeps_history(): void
    {
        $trip = $this->tripWithCoordinateSnapshots();
        $provider = $this->fakeProvider();
        $service = app(TripRouteService::class);

        $first = $service->calculate($trip);
        $same = $service->calculate($trip->refresh());
        $this->assertSame($first->id, $same->id);
        $this->assertSame(1, $provider->routeCalls);

        $provider->routeResult = new RouteResult(
            distanceM: 735_000,
            durationS: 39_600,
            shapePolyline6: null,
            legs: [['summary' => ['length' => 735]]],
            provider: 'fake',
            routingEngineVersion: 'fake-2.0',
            osmDataVersion: 'fixture-2',
        );
        $second = $service->calculate($trip->refresh(), force: true);

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('logistics_trip_routes', 2);
        $this->assertFalse($first->refresh()->is_current);
        $this->assertTrue($second->refresh()->is_current);
        $this->assertSame(735_000, $trip->refresh()->planned_distance_m);

        $trip->stops()->firstOrFail()->update(['address' => 'Новый адрес погрузки']);
        $this->assertSame(RouteStatus::Stale, $second->refresh()->status);
        $this->assertNull($trip->refresh()->route_calculated_at);
    }

    public function test_no_route_creates_a_safe_auditable_route_version(): void
    {
        $trip = $this->tripWithCoordinateSnapshots();
        $provider = $this->fakeProvider();
        $provider->routeException = new NoRouteException;

        try {
            app(TripRouteService::class)->calculate($trip);
            $this->fail('NoRouteException was not thrown.');
        } catch (NoRouteException) {
            // Expected domain result: persisted below and not retried as a transient failure.
        }

        $route = LogisticsTripRoute::query()->sole();
        $this->assertSame(RouteStatus::NoRoute, $route->status);
        $this->assertTrue($route->is_current);
        $this->assertSame('no_route', $route->routing_options['error_code']);
        $this->assertNull($route->distance_m);
    }

    public function test_repeated_trip_job_does_not_duplicate_route_or_progress(): void
    {
        $trip = $this->tripWithCoordinateSnapshots();
        $run = app(RoutingRunService::class)->create(
            RoutingRunType::TripRoute,
            'truck',
            1,
            null,
            ['trip_id' => $trip->id],
        );
        $job = new CalculateTripRouteJob($trip->id, $run->id);

        $job->handle(app(TripRouteService::class), app(RoutingRunService::class));
        $job->handle(app(TripRouteService::class), app(RoutingRunService::class));

        $this->assertDatabaseCount('logistics_trip_routes', 1);
        $this->assertSame(1, $this->fakeProvider()->routeCalls);
        $run->refresh();
        $this->assertSame(1, $run->completed_pairs);
        $this->assertSame(RoutingRunStatus::Completed, $run->status);
    }

    public function test_repeated_route_enqueue_reuses_the_active_run_among_many_other_runs(): void
    {
        Queue::fake();
        $user = $this->logisticsUser();
        $trip = LogisticsTrip::factory()->create();
        $active = app(RoutingRunService::class)->create(
            RoutingRunType::TripRoute,
            'truck',
            1,
            $user->id,
            ['trip_id' => $trip->id],
        );

        foreach (range(1, 60) as $offset) {
            app(RoutingRunService::class)->create(
                RoutingRunType::TripRoute,
                'truck',
                1,
                $user->id,
                ['trip_id' => $trip->id + $offset],
            );
        }

        $this->actingAs($user)
            ->postJson("/api/logistics/trips/{$trip->id}/routes/calculate", ['force' => false])
            ->assertAccepted()
            ->assertJsonPath('data.id', $active->id);

        $this->assertDatabaseCount('logistics_routing_runs', 61);
        Queue::assertNothingPushed();
    }

    public function test_trip_job_fails_once_with_a_clear_validation_error_for_missing_stops(): void
    {
        $trip = LogisticsTrip::factory()->create();
        $run = app(RoutingRunService::class)->create(
            RoutingRunType::TripRoute,
            'truck',
            1,
            null,
            ['trip_id' => $trip->id],
        );

        (new CalculateTripRouteJob($trip->id, $run->id))
            ->handle(app(TripRouteService::class), app(RoutingRunService::class));

        $run->refresh();
        $this->assertSame(RoutingRunStatus::Failed, $run->status);
        $this->assertSame(1, $run->failed_pairs);
        $this->assertStringContainsString('минимум две остановки', (string) $run->last_error);
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

        return $city->load('logisticsSetting');
    }

    private function tripWithCoordinateSnapshots(): LogisticsTrip
    {
        $vehicle = Vehicle::factory()->create();
        $from = $this->city('Маршрут-начало-'.uniqid(), 59.9343, 30.3351);
        $to = $this->city('Маршрут-конец-'.uniqid(), 55.7558, 37.6173);
        $trip = LogisticsTrip::factory()->create(['vehicle_id' => $vehicle->id]);
        $trip->stops()->create([
            'sequence' => 1,
            'city_id' => $from->id,
            'stop_type' => 'origin',
            'latitude' => 59.9343,
            'longitude' => 30.3351,
        ]);
        $trip->stops()->create([
            'sequence' => 2,
            'city_id' => $to->id,
            'stop_type' => 'destination',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        return $trip->refresh();
    }

    private function fakeProvider(): FakeRoutingProvider
    {
        $provider = app(RoutingProviderInterface::class);
        $this->assertInstanceOf(FakeRoutingProvider::class, $provider);

        return $provider;
    }
}
