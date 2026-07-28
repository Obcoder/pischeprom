<?php

namespace Tests\Feature\Logistics;

use App\Models\Check;
use App\Models\Entity;
use App\Models\LogisticsCity;
use App\Models\LogisticsExpenseCategory;
use App\Models\LogisticsTrip;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;

class LogisticsCrudTest extends LogisticsTestCase
{
    public function test_logistics_page_is_available_without_page_specific_authentication(): void
    {
        $this->get('/Ameise/logistics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Logistics'));
    }

    public function test_logistics_page_and_api_are_fully_available_without_authentication_when_disabled(): void
    {
        config(['logistics.authorization_enabled' => false]);

        $this->get('/Ameise/logistics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Logistics')
                ->where('auth.permissions.logistics.view', true)
                ->where('auth.permissions.logistics.trips_manage', true)
                ->where('auth.permissions.logistics.vehicles_manage', true)
                ->where('auth.permissions.logistics.expenses_manage', true)
                ->where('auth.permissions.logistics.matrix_manage', true)
                ->where('auth.permissions.logistics.technical_view', true));

        $this->getJson('/api/logistics/dashboard')->assertOk();

        $this->postJson('/api/logistics/vehicles', $this->vehiclePayload())
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'А123АА78');
    }

    public function test_vehicle_crud_is_authorized_normalized_and_archived_safely(): void
    {
        $this->getJson('/api/logistics/vehicles')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->getJson('/api/logistics/vehicles')
            ->assertForbidden();

        $user = $this->logisticsUser();
        $response = $this->actingAs($user)
            ->postJson('/api/logistics/vehicles', $this->vehiclePayload())
            ->assertCreated()
            ->assertJsonPath('data.registration_number', 'А123АА78')
            ->assertJsonPath('data.vin', 'TESTVIN0001');
        $vehicleId = $response->json('data.id');

        $this->putJson("/api/logistics/vehicles/{$vehicleId}", $this->vehiclePayload([
            'name' => 'Тягач после обновления',
        ]))->assertOk()->assertJsonPath('data.name', 'Тягач после обновления');

        $this->deleteJson("/api/logistics/vehicles/{$vehicleId}")->assertOk();
        $this->assertSoftDeleted('logistics_vehicles', ['id' => $vehicleId]);
        $this->postJson("/api/logistics/vehicles/{$vehicleId}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);
    }

    public function test_trip_validates_capacity_dates_and_calculates_odometer_distance(): void
    {
        $user = $this->logisticsUser();
        $vehicle = Vehicle::factory()->create(['payload_capacity_kg' => 10_000]);
        $from = $this->city('Санкт-Петербург', 59.9343, 30.3351);
        $middle = $this->city('Тверь', 56.8587, 35.9176);
        $to = $this->city('Москва', 55.7558, 37.6173);

        $this->actingAs($user)->postJson('/api/logistics/trips', $this->tripPayload($from, $to, [
            'vehicle_id' => $vehicle->id,
            'cargo_weight_kg' => 10_001,
        ]))->assertUnprocessable()->assertJsonValidationErrors('cargo_weight_kg');

        $this->postJson('/api/logistics/trips', $this->tripPayload($from, $to, [
            'planned_departure_at' => '2026-08-02 10:00:00',
            'planned_arrival_at' => '2026-08-02 09:00:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('planned_arrival_at');

        $response = $this->postJson('/api/logistics/trips', $this->tripPayload($from, $to, [
            'vehicle_id' => $vehicle->id,
            'actual_departure_at' => '2026-08-01 08:15:00',
            'actual_arrival_at' => '2026-08-01 17:45:00',
            'odometer_start_km' => 1000.0,
            'odometer_end_km' => 1050.5,
            'stops' => [
                ['city_id' => $from->id, 'operation_type' => 'loading'],
                ['city_id' => $middle->id, 'operation_type' => 'technical'],
                ['city_id' => $to->id, 'operation_type' => 'unloading'],
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.actual_distance_m', 50_500)
            ->assertJsonPath('data.actual_distance_km', 50.5)
            ->assertJsonPath('data.actual_distance_source', 'odometer')
            ->assertJsonPath('data.route_summary', 'Санкт-Петербург → Тверь → Москва')
            ->assertJsonPath('data.stops.0.stop_type', 'origin')
            ->assertJsonPath('data.stops.1.stop_type', 'waypoint')
            ->assertJsonPath('data.stops.2.stop_type', 'destination');

        $tripId = $response->json('data.id');
        $middleStopId = $response->json('data.stops.1.id');
        $this->assertMatchesRegularExpression('/^TR-2026-\d{6}$/', $response->json('data.number'));

        $this->postJson("/api/logistics/trips/{$tripId}/stops/{$middleStopId}/move", ['direction' => 'up'])
            ->assertOk()
            ->assertJsonPath('data.stops.0.id', $middleStopId)
            ->assertJsonPath('data.stops.0.stop_type', 'origin');
    }

    public function test_trip_list_defaults_to_actual_departure_and_filters_by_actual_dates(): void
    {
        $withoutActualDate = LogisticsTrip::factory()->create([
            'planned_departure_at' => '2026-08-30 08:00:00',
            'actual_departure_at' => null,
            'actual_arrival_at' => null,
        ]);
        $olderActualDate = LogisticsTrip::factory()->create([
            'planned_departure_at' => '2026-08-20 08:00:00',
            'actual_departure_at' => '2026-08-01 08:00:00',
            'actual_arrival_at' => '2026-08-01 18:00:00',
        ]);
        $newerActualDate = LogisticsTrip::factory()->create([
            'planned_departure_at' => '2026-07-01 08:00:00',
            'actual_departure_at' => '2026-08-03 09:00:00',
            'actual_arrival_at' => '2026-08-03 19:00:00',
        ]);

        $this->actingAs($this->logisticsUser())
            ->getJson('/api/logistics/trips')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerActualDate->id)
            ->assertJsonPath('data.1.id', $olderActualDate->id)
            ->assertJsonPath('data.2.id', $withoutActualDate->id)
            ->assertJsonPath('data.0.actual_departure_at', '2026-08-03T06:00:00.000000Z')
            ->assertJsonPath('data.0.actual_arrival_at', '2026-08-03T16:00:00.000000Z');

        $this->getJson('/api/logistics/trips?date_from=2026-08-03&date_to=2026-08-03')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newerActualDate->id);
    }

    public function test_vehicle_in_maintenance_requires_explicit_trip_acknowledgement(): void
    {
        $user = $this->logisticsUser();
        $vehicle = Vehicle::factory()->create(['status' => 'maintenance']);
        $from = $this->city('Псков', 57.8193, 28.3318);
        $to = $this->city('Новгород', 58.5228, 31.2699);
        $payload = $this->tripPayload($from, $to, [
            'status' => 'planned',
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($user)->postJson('/api/logistics/trips', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        $this->postJson('/api/logistics/trips', [
            ...$payload,
            'acknowledge_vehicle_warning' => true,
        ])->assertCreated();
    }

    public function test_check_allocations_metrics_and_delete_guards_preserve_the_check(): void
    {
        $user = $this->logisticsUser();
        $entity = Entity::query()->create(['name' => 'АЗС Тест']);
        $check = Check::query()->create(['date' => '2026-08-01', 'entity_id' => $entity->id, 'amount' => 100]);
        $trip = LogisticsTrip::factory()->create([
            'actual_distance_m' => 100_000,
            'actual_distance_source' => 'manual',
            'cargo_weight_kg' => 1_000,
        ]);
        $fuel = LogisticsExpenseCategory::query()->where('code', 'fuel')->firstOrFail();
        $other = LogisticsExpenseCategory::query()->where('code', 'other')->firstOrFail();

        $this->actingAs($user)->postJson("/api/logistics/trips/{$trip->id}/expenses", [
            'check_id' => $check->id,
            'expense_category_id' => $fuel->id,
            'allocated_amount' => 60,
            'quantity' => 20,
            'unit' => 'l',
        ])->assertCreated()->assertJsonPath('data.has_check', true);
        $second = $this->postJson("/api/logistics/trips/{$trip->id}/expenses", [
            'check_id' => $check->id,
            'expense_category_id' => $other->id,
            'allocated_amount' => 40,
        ])->assertCreated();

        $this->postJson("/api/logistics/trips/{$trip->id}/expenses", [
            'check_id' => $check->id,
            'expense_category_id' => $other->id,
            'allocated_amount' => 0.01,
        ])->assertUnprocessable()->assertJsonValidationErrors('allocated_amount');

        $this->postJson("/api/logistics/trips/{$trip->id}/expenses", [
            'check_id' => $check->id,
            'expense_category_id' => $other->id,
            'allocated_amount' => 1,
            'currency_code' => 'EUR',
        ])->assertUnprocessable()->assertJsonValidationErrors('currency_code');

        $this->getJson("/api/logistics/trips/{$trip->id}/expenses")
            ->assertOk()
            ->assertJsonPath('metrics.total_expenses', 100)
            ->assertJsonPath('metrics.cost_per_km', 1)
            ->assertJsonPath('metrics.cost_per_kg', 0.1)
            ->assertJsonPath('metrics.fuel_liters', 20)
            ->assertJsonPath('metrics.actual_fuel_consumption_l_per_100km', 20);

        $this->deleteJson("/api/checks/{$check->id}")->assertStatus(409);
        $this->deleteJson("/api/logistics/trips/{$trip->id}/expenses/{$second->json('data.id')}")->assertOk();
        $this->assertDatabaseHas('checks', ['id' => $check->id]);
        $this->deleteJson("/api/logistics/trips/{$trip->id}")->assertOk();
        $this->assertDatabaseHas('checks', ['id' => $check->id]);
    }

    public function test_vehicle_used_by_a_trip_cannot_be_force_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();
        LogisticsTrip::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->expectException(LogicException::class);
        $vehicle->forceDelete();
    }

    public function test_completed_trip_requires_technical_permission_for_changes(): void
    {
        $manager = $this->logisticsUser(['logistics.view', 'logistics.trips.manage']);
        $trip = LogisticsTrip::factory()->create(['status' => 'completed']);
        $from = $this->city('Смоленск', 54.7826, 32.0453);
        $to = $this->city('Тверь-2', 56.8587, 35.9176);

        $this->actingAs($manager)
            ->putJson("/api/logistics/trips/{$trip->id}", $this->tripPayload($from, $to, ['status' => 'completed']))
            ->assertForbidden();
    }

    public function test_existing_city_can_be_enabled_with_a_verified_routing_snapshot(): void
    {
        $user = $this->logisticsUser();
        $city = $this->city('Петрозаводск', 61.7849, 34.3469);

        $this->actingAs($user)
            ->putJson("/api/logistics/cities/{$city->id}", [
                'is_matrix_enabled' => true,
                'mark_verified' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.city_id', $city->id)
            ->assertJsonPath('data.routing_latitude', 61.7849)
            ->assertJsonPath('data.routing_longitude', 34.3469)
            ->assertJsonPath('data.coordinate_source', 'existing')
            ->assertJsonPath('data.is_matrix_enabled', true)
            ->assertJsonPath('data.is_verified', true);

        $setting = LogisticsCity::query()->where('city_id', $city->id)->sole();
        $this->assertSame($user->id, $setting->coordinates_verified_by);
        $this->assertNotNull($setting->coordinates_verified_at);

        $withoutPoint = $this->city('Город без точки', 60.0, 30.0);
        $withoutPoint->update(['latitude' => null, 'longitude' => null]);

        $this->putJson("/api/logistics/cities/{$withoutPoint->id}", [
            'is_matrix_enabled' => true,
            'mark_verified' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('routing_latitude');
    }
}
