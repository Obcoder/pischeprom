<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuildingApartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_apartment_crud_is_scoped_to_building_and_preserves_string_numbers(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 1']);
        $otherBuilding = Building::query()->create(['address' => 'Мира, 2']);
        $base = "/api/buildings/{$building->id}/apartments";

        $created = $this->postJson($base, ['number' => ' 01А/2 ', 'type' => 'office'])
            ->assertCreated()
            ->assertJsonPath('number', '01А/2')
            ->assertJsonPath('label', 'офис 01А/2')
            ->assertJsonPath('building_id', $building->id);
        $id = $created->json('id');

        $this->getJson($base)->assertOk()->assertJsonPath('0.id', $id);
        $this->getJson("$base/$id")->assertOk()->assertJsonPath('id', $id);
        $this->getJson("/api/buildings/{$otherBuilding->id}/apartments/$id")->assertNotFound();
        $this->putJson("/api/buildings/{$otherBuilding->id}/apartments/$id", ['number' => '9'])->assertNotFound();
        $this->deleteJson("/api/buildings/{$otherBuilding->id}/apartments/$id")->assertNotFound();
        $this->postJson($base, ['number' => '01А/2', 'type' => 'office'])->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->postJson($base, ['number' => '01А/2', 'type' => 'apartment'])->assertCreated();
        $this->postJson($base, ['number' => '2', 'type' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->putJson("$base/$id", ['number' => 'Б-7', 'type' => 'premise'])
            ->assertOk()->assertJsonPath('label', 'пом. Б-7');
        $this->getJson('/api/buildings/'.$building->id)->assertOk()->assertJsonCount(2, 'apartments');
        $this->deleteJson("$base/$id")->assertNoContent();
        $this->assertDatabaseMissing('apartments', ['id' => $id]);
    }

    public function test_apartment_migration_rolls_back_and_reapplies_without_losing_building_associations(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 17']);
        $entity = Entity::query()->create(['name' => 'Покупатель']);
        $unit = Unit::query()->create(['name' => 'Компания']);
        $order = Order::query()->create(['entity_id' => $entity->id, 'currency_code' => 'RUB']);
        foreach ([$entity, $unit, $order] as $owner) {
            $owner->buildings()->attach($building->id);
        }
        $migration = require database_path('migrations/2026_09_28_120000_create_apartments_and_building_selections.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('apartments'));
        foreach (['building_order', 'building_unit', 'building_entities'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'apartment_id'));
            $this->assertDatabaseHas($table, ['building_id' => $building->id]);
        }

        $migration->up();
        $this->assertTrue(Schema::hasTable('apartments'));
        foreach ([$entity, $unit, $order] as $owner) {
            $selected = $owner->buildings()->firstOrFail();
            $this->assertSame($building->id, $selected->id);
            $this->assertNull($selected->apartment);
        }
    }

    public function test_order_selection_survives_legacy_updates_and_can_be_cleared(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 3']);
        $apartment = $building->apartments()->create(['number' => '12А', 'type' => 'apartment']);
        $payload = $this->orderPayload($building);
        $created = $this->postJson('/api/orders', [
            ...$payload, 'building_apartments' => [$building->id => $apartment->id],
        ])->assertCreated()
            ->assertJsonPath('data.buildings.0.apartment_id', $apartment->id)
            ->assertJsonPath('data.buildings.0.apartment.label', 'кв. 12А');
        $orderId = $created->json('data.id');

        $this->assertDatabaseHas('building_entities', [
            'entity_id' => $payload['entity_id'], 'building_id' => $building->id, 'apartment_id' => $apartment->id,
        ]);
        $this->putJson('/api/orders/'.$orderId, $payload)->assertOk()
            ->assertJsonPath('data.buildings.0.apartment_id', $apartment->id);
        $this->getJson('/api/orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.buildings.0.apartment.number', '12А');
        $this->getJson('/api/orders/options')->assertOk()
            ->assertJsonPath('buildings.0.apartments.0.id', $apartment->id);

        $this->putJson('/api/orders/'.$orderId, [
            ...$payload, 'building_apartments' => [$building->id => null],
        ])->assertOk()->assertJsonPath('data.buildings.0.apartment', null);
        $this->assertDatabaseHas('building_order', ['order_id' => $orderId, 'apartment_id' => null]);
        $this->assertDatabaseHas('building_entities', [
            'entity_id' => $payload['entity_id'], 'apartment_id' => $apartment->id,
        ]);
    }

    public function test_order_rejects_apartment_from_another_or_unselected_building_atomically(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 4']);
        $other = Building::query()->create(['address' => 'Мира, 5']);
        $apartment = $other->apartments()->create(['number' => '1', 'type' => 'apartment']);
        $payload = $this->orderPayload($building);

        foreach ([$building->id, $other->id] as $buildingId) {
            $this->postJson('/api/orders', [
                ...$payload, 'building_apartments' => [$buildingId => $apartment->id],
            ])->assertUnprocessable()->assertJsonValidationErrors('building_apartments.'.$buildingId);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_entity_selection_is_exposed_preserved_and_validated(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 6']);
        $apartment = $building->apartments()->create(['number' => '10', 'type' => 'office']);
        $other = Building::query()->create(['address' => 'Мира, 7']);
        $payload = ['name' => 'Компания', 'buildings' => [$building->id]];
        $created = $this->postJson('/api/entities', [
            ...$payload, 'building_apartments' => [$building->id => $apartment->id],
        ])->assertOk()->assertJsonPath('data.buildings.0.pivot.apartment_id', $apartment->id)
            ->assertJsonPath('data.buildings.0.apartment.label', 'офис 10')
            ->assertJsonCount(1, 'data.buildings.0.apartments');
        $id = $created->json('data.id');
        $this->putJson('/api/entities/'.$id, $payload)->assertOk()
            ->assertJsonPath('data.buildings.0.apartment_id', $apartment->id);
        $this->putJson('/api/entities/'.$id, [
            ...$payload, 'buildings' => [$other->id], 'building_apartments' => [$other->id => $apartment->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('building_apartments.'.$other->id);
        $this->assertDatabaseHas('building_entities', ['entity_id' => $id, 'apartment_id' => $apartment->id]);
        $this->putJson('/api/entities/'.$id, [
            ...$payload, 'building_apartments' => [$building->id => null],
        ])->assertOk()->assertJsonPath('data.buildings.0.apartment', null);
    }

    public function test_unit_attachment_updates_apartment_and_preserves_omitted_selection(): void
    {
        $unit = Unit::query()->create(['name' => 'Компания']);
        $building = Building::query()->create(['address' => 'Мира, 8']);
        $apartment = $building->apartments()->create(['number' => '20', 'type' => 'premise']);
        $other = Building::query()->create(['address' => 'Мира, 9']);
        $url = "/api/units/{$unit->id}/buildings";

        $this->postJson($url, ['building_id' => $building->id, 'apartment_id' => $apartment->id])
            ->assertOk()->assertJsonPath('data.pivot.apartment_id', $apartment->id)
            ->assertJsonPath('data.apartment.label', 'пом. 20');
        $this->postJson($url, ['building_id' => $building->id])->assertOk()
            ->assertJsonPath('data.pivot.apartment_id', $apartment->id);
        $this->postJson($url, ['building_id' => $other->id, 'apartment_id' => $apartment->id])
            ->assertUnprocessable()->assertJsonValidationErrors('apartment_id');
        $this->assertDatabaseMissing('building_unit', ['unit_id' => $unit->id, 'building_id' => $other->id]);
        $this->postJson($url, ['building_id' => $building->id, 'apartment_id' => null])
            ->assertOk()->assertJsonPath('data.apartment', null);
        $this->assertDatabaseCount('building_unit', 1);
    }

    public function test_unit_creation_accepts_apartment_selection(): void
    {
        Storage::fake('yandex');
        $building = Building::query()->create(['address' => 'Мира, 10']);
        $apartment = $building->apartments()->create(['number' => '21', 'type' => 'office']);
        $created = $this->postJson('/api/units', [
            'name' => 'Компания', 'buildings' => [$building->id],
            'building_apartments' => [$building->id => $apartment->id],
        ])->assertCreated();
        $this->assertDatabaseHas('building_unit', [
            'unit_id' => $created->json('id'), 'building_id' => $building->id, 'apartment_id' => $apartment->id,
        ]);
    }

    public function test_legacy_building_unit_endpoint_validates_apartment_ownership(): void
    {
        $unit = Unit::query()->create(['name' => 'Компания']);
        $building = Building::query()->create(['address' => 'Мира, 15']);
        $other = Building::query()->create(['address' => 'Мира, 16']);
        $apartment = $building->apartments()->create(['number' => '7', 'type' => 'office']);
        $this->postJson('/api/building_units', [
            'unit_id' => $unit->id, 'building_id' => $other->id, 'apartment_id' => $apartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('apartment_id');
        $this->assertDatabaseCount('building_unit', 0);
        $this->postJson('/api/building_units', [
            'unit_id' => $unit->id, 'building_id' => $building->id, 'apartment_id' => $apartment->id,
        ])->assertCreated();
        $this->assertDatabaseHas('building_unit', [
            'unit_id' => $unit->id, 'building_id' => $building->id, 'apartment_id' => $apartment->id,
        ]);
    }

    public function test_deletion_is_blocked_for_apartments_referenced_by_orders_entities_or_units(): void
    {
        $building = Building::query()->create(['address' => 'Мира, 11']);
        $apartment = $building->apartments()->create(['number' => '30', 'type' => 'apartment']);
        $entity = Entity::query()->create(['name' => 'Компания']);
        $unit = Unit::query()->create(['name' => 'Unit']);
        $order = Order::query()->create(['entity_id' => $entity->id, 'currency_code' => 'RUB']);
        foreach ([$entity, $unit, $order] as $owner) {
            $owner->buildings()->attach($building->id, ['apartment_id' => $apartment->id]);
            $this->deleteJson("/api/buildings/{$building->id}/apartments/{$apartment->id}")->assertConflict();
            $this->deleteJson("/api/buildings/{$building->id}")->assertConflict();
            $this->assertDatabaseHas('apartments', ['id' => $apartment->id]);
            $owner->buildings()->detach();
        }
        $this->deleteJson("/api/buildings/{$building->id}/apartments/{$apartment->id}")->assertNoContent();
    }

    private function orderPayload(Building $building): array
    {
        return [
            'entity_id' => Entity::query()->create(['name' => 'Покупатель'])->id,
            'order_status_id' => OrderStatus::query()->where('code', OrderStatus::OPEN)->value('id'),
            'currency_code' => 'RUB',
            'building_ids' => [$building->id],
            'items' => [['good_id' => Good::query()->create(['name' => 'Товар'])->id, 'quantity' => 1]],
        ];
    }
}
