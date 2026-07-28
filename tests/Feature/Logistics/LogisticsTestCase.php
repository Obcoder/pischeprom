<?php

namespace Tests\Feature\Logistics;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\LogisticsExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class LogisticsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'logistics.authorization_enabled' => true,
            'logistics.routing_driver' => 'fake',
            'logistics.queue_connection' => null,
            'logistics.lock_store' => null,
            'logistics.osm_data_version' => 'test-osm-1',
            'inertia.ssr.enabled' => false,
        ]);
        $this->seed(LogisticsExpenseCategorySeeder::class);
    }

    protected function logisticsUser(array $permissions = []): User
    {
        $permissions = $permissions ?: [
            'logistics.view',
            'logistics.trips.manage',
            'logistics.vehicles.manage',
            'logistics.expenses.manage',
            'logistics.matrix.manage',
            'logistics.technical.view',
        ];
        $user = User::factory()->create(['status' => 'active']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'crm']);
        }

        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function city(string $name, float $latitude, float $longitude): City
    {
        $country = Country::query()->firstOrCreate(
            ['name' => 'Россия'],
            ['сodeISO' => 'RU']
        );
        $region = Region::query()->firstOrCreate(
            ['name' => 'Тестовый регион'],
            ['country_id' => $country->id]
        );

        return City::query()->create([
            'name' => $name,
            'region_id' => $region->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    protected function vehiclePayload(array $overrides = []): array
    {
        return [
            'name' => 'Тестовый тягач',
            'registration_number' => 'А 123 АА 78',
            'make' => 'Volvo',
            'model' => 'FH',
            'year' => 2022,
            'vin' => 'TEST VIN 0001',
            'vehicle_type' => 'truck',
            'status' => 'active',
            'payload_capacity_kg' => 20_000,
            'cargo_volume_m3' => 82,
            'curb_weight_kg' => 12_000,
            'gross_weight_kg' => 32_000,
            'length_m' => 16.5,
            'width_m' => 2.55,
            'height_m' => 4,
            'axle_count' => 5,
            'max_axle_load_t' => 10,
            'fuel_type' => 'diesel',
            'fuel_tank_capacity_l' => 600,
            'average_fuel_consumption_l_per_100km' => 32,
            'is_active' => true,
            ...$overrides,
        ];
    }

    protected function tripPayload(City $from, City $to, array $overrides = []): array
    {
        return [
            'status' => 'draft',
            'planned_departure_at' => '2026-08-01 08:00:00',
            'planned_arrival_at' => '2026-08-01 18:00:00',
            'cargo_description' => 'Тестовый груз',
            'cargo_weight_kg' => 10_000,
            'routing_profile' => 'truck',
            'stops' => [
                ['city_id' => $from->id, 'operation_type' => 'loading'],
                ['city_id' => $to->id, 'operation_type' => 'unloading'],
            ],
            ...$overrides,
        ];
    }
}
