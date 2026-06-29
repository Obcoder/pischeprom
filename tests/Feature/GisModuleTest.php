<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\EntityLocation;
use App\Services\Gis\EntityLocationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GisModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'gis.default_provider' => '2gis',
            'gis.providers.2gis.api_key' => null,
            'gis.providers.yandex.api_key' => null,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        $this->createSchema();
    }

    public function test_entity_location_service_saves_manual_coordinates(): void
    {
        $entity = $this->createEntity('ООО Ромашка');

        $location = app(EntityLocationService::class)->saveManualLocation($entity, [
            'address_text' => 'Москва, Тверская 1',
            'lat' => 55.757617,
            'lon' => 37.615619,
        ]);

        $this->assertInstanceOf(EntityLocation::class, $location);
        $this->assertSame('manual', $location->source);
        $this->assertTrue($location->is_confirmed);
        $this->assertSame(55.757617, $location->lat);
        $this->assertDatabaseHas('entity_locations', [
            'entity_id' => $entity->id,
            'source' => 'manual',
            'is_confirmed' => 1,
        ]);
    }

    public function test_gis_entities_endpoint_returns_normalized_items(): void
    {
        $classificationId = DB::table('entity_classifications')->insertGetId([
            'name' => 'client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Москва']);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entity = $this->createEntity('ООО Клиент', [
            'entity_classification_id' => $classificationId,
            'legal_address' => 'Москва, Никольская 1',
        ]);

        DB::table('city_entity')->insert([
            'city_id' => $cityId,
            'entity_id' => $entity->id,
        ]);
        DB::table('entity_user')->insert([
            'entity_id' => $entity->id,
            'user_id' => $userId,
            'role' => 'owner',
            'status' => 'active',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        EntityLocation::query()->create([
            'entity_id' => $entity->id,
            'address_text' => 'Москва, Никольская 1',
            'lat' => 55.754047,
            'lon' => 37.621405,
            'source' => 'manual',
            'precision_level' => 'exact',
            'is_confirmed' => true,
        ]);

        $this->getJson('/api/gis/entities?manager_id='.$userId)
            ->assertOk()
            ->assertJsonPath('items.0.entity_id', $entity->id)
            ->assertJsonPath('items.0.name', 'ООО Клиент')
            ->assertJsonPath('items.0.type', 'client')
            ->assertJsonPath('items.0.status', 'active')
            ->assertJsonPath('items.0.city', 'Москва')
            ->assertJsonPath('items.0.is_confirmed', true);
    }

    public function test_route_preview_normalizes_entity_points(): void
    {
        $first = $this->createEntity('Точка 1');
        $second = $this->createEntity('Точка 2');

        EntityLocation::query()->create([
            'entity_id' => $first->id,
            'address_text' => 'Москва',
            'lat' => 55.751244,
            'lon' => 37.618423,
            'source' => 'manual',
            'precision_level' => 'exact',
            'is_confirmed' => true,
        ]);
        EntityLocation::query()->create([
            'entity_id' => $second->id,
            'address_text' => 'Санкт-Петербург',
            'lat' => 59.93863,
            'lon' => 30.31413,
            'source' => 'manual',
            'precision_level' => 'exact',
            'is_confirmed' => true,
        ]);

        $this->postJson('/api/gis/routes/preview', [
            'provider' => '2gis',
            'transport_mode' => 'car',
            'points' => [
                ['entity_id' => $first->id],
                ['entity_id' => $second->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('provider', '2gis')
            ->assertJsonPath('points.0.entity_id', $first->id)
            ->assertJsonPath('points.0.sequence_no', 1)
            ->assertJsonPath('points.1.entity_id', $second->id)
            ->assertJsonPath('route_geometry_json.type', 'LineString')
            ->assertJsonPath('provider_response_summary.status', 'stub')
            ->assertJson(fn ($json) => $json->where('distance_m', fn ($value) => $value > 0)->etc());
    }

    public function test_distance_matrix_returns_local_estimates(): void
    {
        $this->postJson('/api/gis/routes/distance-matrix', [
            'provider' => 'yandex',
            'transport_mode' => 'truck',
            'origins' => [
                [
                    'title' => 'Склад',
                    'lat' => 55.751244,
                    'lon' => 37.618423,
                    'point_type' => 'warehouse',
                ],
            ],
            'destinations' => [
                [
                    'title' => 'Клиент',
                    'lat' => 59.93863,
                    'lon' => 30.31413,
                    'point_type' => 'manual',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('provider', 'yandex')
            ->assertJsonPath('summary.status', 'local_estimate')
            ->assertJsonPath('summary.transport_mode', 'truck')
            ->assertJsonPath('matrix.0.0.origin_index', 0)
            ->assertJsonPath('matrix.0.0.destination_index', 0)
            ->assertJson(fn ($json) => $json
                ->where('matrix.0.0.distance_m', fn ($value) => $value > 0)
                ->where('matrix.0.0.duration_sec', fn ($value) => $value > 0)
                ->etc());
    }

    public function test_geocode_endpoint_returns_clear_error_when_api_key_missing(): void
    {
        $entity = $this->createEntity('ООО Без ключа', [
            'legal_address' => 'Москва, Тверская 1',
        ]);

        $this->postJson('/api/gis/entities/'.$entity->id.'/geocode', [
            'provider' => 'yandex',
        ])
            ->assertStatus(503)
            ->assertJsonPath('error.message', 'API-ключ Яндекс Карт не задан.');
    }

    private function createSchema(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('entity_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('full_name', 1024)->nullable();
            $table->foreignId('entity_classification_id')->nullable()->constrained('entity_classifications')->nullOnDelete();
            $table->string('INN')->nullable();
            $table->string('KPP')->nullable();
            $table->string('OGRN')->nullable();
            $table->string('legal_address', 1024)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->json('dadata_raw')->nullable();
            $table->timestamp('dadata_loaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('building_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('building_type_id')->nullable()->constrained('building_types')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('postcode')->nullable();
            $table->timestamps();
        });

        Schema::create('building_entities', function (Blueprint $table) {
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
        });

        Schema::create('city_entity', function (Blueprint $table) {
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('owner');
            $table->string('status', 32)->default('active');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });

        Schema::create('entity_locations', function (Blueprint $table) {
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->primary('entity_id');
            $table->string('address_text', 1024)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('provider_object_id')->nullable();
            $table->string('precision_level', 32)->default('unknown');
            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('geocoded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gis_route_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('provider', 32);
            $table->string('transport_mode', 32)->default('car');
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->json('route_geometry_json')->nullable();
            $table->json('provider_response_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gis_route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_draft_id')->constrained('gis_route_drafts')->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('address_text', 1024)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->string('point_type', 32)->default('manual');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createEntity(string $name, array $attributes = []): Entity
    {
        return Entity::query()->create([
            'name' => $name,
            ...$attributes,
        ]);
    }
}
