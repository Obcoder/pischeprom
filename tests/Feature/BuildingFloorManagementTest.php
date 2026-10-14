<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BuildingFloorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_floor_crud_keeps_numbers_unique_and_scoped_to_the_building(): void
    {
        $building = Building::create(['address' => 'Мира, 10']);
        $other = Building::create(['address' => 'Мира, 11']);
        $url = "/api/buildings/{$building->id}/floors";
        $floor = $this->postJson($url, ['number' => -1, 'name' => 'Подземный', 'notes' => 'Вход со двора'])
            ->assertCreated()->assertJsonPath('number', -1)->json('id');
        $this->postJson($url, ['number' => -1])->assertUnprocessable();
        $this->postJson($url, ['number' => 301])->assertUnprocessable();
        $this->postJson($url, ['number' => 1.5])->assertUnprocessable();
        $this->getJson($url)->assertOk()->assertJsonPath('0.id', $floor)->assertJsonPath('0.apartments_count', 0);
        $this->putJson("$url/$floor", ['number' => 0, 'name' => 'Цоколь'])->assertOk()->assertJsonPath('number', 0);
        $this->putJson("/api/buildings/{$other->id}/floors/$floor", ['number' => 1])->assertNotFound();
        $this->deleteJson("/api/buildings/{$other->id}/floors/$floor")->assertNotFound();
        $this->deleteJson("$url/$floor")->assertNoContent();
        $this->assertDatabaseMissing('building_floors', ['id' => $floor]);
    }

    public function test_rooms_can_move_between_floors_and_unassigned_group_without_losing_entities(): void
    {
        $building = Building::create(['address' => 'Мира, 12']);
        $floor = $building->floors()->create(['number' => 1]);
        $upper = $building->floors()->create(['number' => 2]);
        $other = Building::create(['address' => 'Другой дом'])->floors()->create(['number' => 1]);
        $url = "/api/buildings/{$building->id}/apartments";
        $room = $this->postJson($url, ['number' => '01А', 'type' => 'office', 'building_floor_id' => $floor->id, 'notes' => 'Левая дверь'])
            ->assertCreated()->assertJsonPath('building_floor_id', $floor->id)->json('id');
        $entity = Entity::create(['name' => 'Арендатор']);
        $this->postJson("$url/$room/entities", ['entity_id' => $entity->id])->assertOk();
        $this->deleteJson("/api/buildings/{$building->id}/floors/{$floor->id}")->assertConflict();
        $this->putJson("$url/$room", ['number' => '01А', 'type' => 'office', 'building_floor_id' => $other->id])->assertUnprocessable();
        $this->putJson("$url/$room", ['number' => '01А', 'type' => 'office', 'building_floor_id' => $upper->id])
            ->assertOk()->assertJsonPath('building_floor_id', $upper->id);
        // An older editor omitting the floor and notes must preserve both fields.
        $this->putJson("$url/$room", ['number' => '01Б', 'type' => 'office'])
            ->assertOk()->assertJsonPath('building_floor_id', $upper->id)->assertJsonPath('notes', 'Левая дверь');
        $this->putJson("$url/$room", ['number' => '01Б', 'type' => 'office', 'building_floor_id' => null])
            ->assertOk()->assertJsonPath('building_floor_id', null);
        $this->assertDatabaseHas('apartment_entity', ['apartment_id' => $room, 'entity_id' => $entity->id]);
        $this->deleteJson("/api/buildings/{$building->id}/floors/{$upper->id}")->assertNoContent();
    }

    public function test_entity_can_link_to_multiple_rooms_and_each_room_to_multiple_entities_without_building_links(): void
    {
        $building = Building::create(['address' => 'Лесная, 1']);
        $first = $building->apartments()->create(['number' => '1']);
        $second = $building->apartments()->create(['number' => '2']);
        $entity = Entity::create(['name' => 'Две квартиры']);
        $neighbour = Entity::create(['name' => 'Сосед']);
        $url = "/api/buildings/{$building->id}/apartments";
        foreach ([$first, $first, $second] as $room) {
            $this->postJson("$url/{$room->id}/entities", ['entity_id' => $entity->id])->assertOk();
        }
        $this->postJson("$url/{$first->id}/entities", ['entity_id' => $neighbour->id])->assertOk();
        $this->assertDatabaseCount('apartment_entity', 3);
        $this->assertDatabaseCount('building_entities', 0);
        $this->getJson("/api/entities/{$entity->id}")->assertOk()->assertJsonCount(2, 'data.apartments');
        $this->getJson("/api/buildings/{$building->id}/structure")->assertOk()->assertJsonCount(2, 'apartments.0.entities');
        $this->deleteJson("$url/{$first->id}/entities/{$entity->id}")->assertNoContent();
        $this->assertDatabaseHas('apartment_entity', ['apartment_id' => $second->id, 'entity_id' => $entity->id]);
        $this->assertDatabaseHas('apartment_entity', ['apartment_id' => $first->id, 'entity_id' => $neighbour->id]);
    }

    public function test_entity_links_are_scoped_and_protect_used_rooms_and_buildings_from_deletion(): void
    {
        $building = Building::create(['address' => 'Защищённое здание']);
        $other = Building::create(['address' => 'Другое здание']);
        $floor = $building->floors()->create(['number' => 1]);
        $room = $building->apartments()->create(['number' => '1', 'building_floor_id' => $floor->id]);
        $entity = Entity::create(['name' => 'Entity']);
        $this->postJson("/api/buildings/{$other->id}/apartments/{$room->id}/entities", ['entity_id' => $entity->id])->assertNotFound();
        $this->postJson("/api/buildings/{$building->id}/apartments/{$room->id}/entities", ['entity_id' => $entity->id])->assertOk();
        $this->deleteJson("/api/buildings/{$other->id}/apartments/{$room->id}/entities/{$entity->id}")->assertNotFound();
        $this->deleteJson("/api/buildings/{$building->id}/apartments/{$room->id}")->assertConflict();
        $this->deleteJson("/api/buildings/{$building->id}")->assertConflict();
        $this->deleteJson("/api/buildings/{$building->id}/apartments/{$room->id}/entities/{$entity->id}")->assertNoContent();
        $this->deleteJson("/api/buildings/{$building->id}")->assertNoContent();
        $this->assertDatabaseMissing('building_floors', ['id' => $floor->id]);
        $this->assertDatabaseMissing('apartments', ['id' => $room->id]);
    }

    public function test_direct_room_links_appear_in_entity_addresses_search_and_geography_filters(): void
    {
        $country = \App\Models\Country::create(['name' => 'Страна', 'сodeISO' => 'ZZ', 'сodeTelefon' => '999']);
        $region = \App\Models\Region::create(['name' => 'Регион', 'country_id' => $country->id]);
        $city = \App\Models\City::create(['name' => 'Город', 'region_id' => $region->id]);
        $building = Building::create(['address' => 'Комната-поиск, 9', 'city_id' => $city->id]);
        $floor = $building->floors()->create(['number' => 5]);
        $room = $building->apartments()->create(['number' => '51', 'type' => 'office', 'building_floor_id' => $floor->id]);
        $entity = Entity::create(['name' => 'Только помещение']);
        Entity::create(['name' => 'Без адреса']);
        $room->entities()->attach($entity);

        foreach ([['search' => 'Комната-поиск'], ['country_ids' => [$country->id]], ['region_ids' => [$region->id]],
            ['city_ids' => [$city->id]], ['building_ids' => [$building->id]]] as $filters) {
            $this->getJson('/api/entities?'.http_build_query($filters))->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $entity->id)
                ->assertJsonPath('data.0.apartments.0.number', '51')
                ->assertJsonPath('data.0.apartments.0.floor.number', 5)
                ->assertJsonPath('data.0.apartments.0.geography.region.country.name', 'Страна');
        }
        $this->assertDatabaseCount('building_entities', 0);
    }

    public function test_legacy_address_can_be_explicitly_refined_to_a_room_and_detaching_clears_old_selection(): void
    {
        $building = Building::create(['address' => 'Старый адрес']);
        $room = $building->apartments()->create(['number' => '5']);
        $entity = Entity::create(['name' => 'Уточнить']);
        $entity->buildings()->attach($building->id);
        $this->getJson("/api/buildings/{$building->id}/structure")->assertJsonPath('unassigned_entities.0.id', $entity->id);
        $this->deleteJson("/api/buildings/{$building->id}")->assertConflict();
        $this->assertDatabaseHas('building_entities', ['building_id' => $building->id, 'entity_id' => $entity->id]);
        $this->postJson("/api/buildings/{$building->id}/apartments/{$room->id}/entities", ['entity_id' => $entity->id, 'replace_building_link' => true])->assertOk();
        $this->assertDatabaseMissing('building_entities', ['building_id' => $building->id, 'entity_id' => $entity->id]);
        $this->assertDatabaseHas('apartment_entity', ['apartment_id' => $room->id, 'entity_id' => $entity->id]);
        $entity->buildings()->attach($building->id, ['apartment_id' => $room->id]);
        $this->deleteJson("/api/buildings/{$building->id}/apartments/{$room->id}/entities/{$entity->id}")->assertNoContent();
        $this->assertDatabaseHas('building_entities', ['building_id' => $building->id, 'entity_id' => $entity->id, 'apartment_id' => null]);
    }

    public function test_workspace_filters_and_stats_cover_the_entire_selection_not_just_page(): void
    {
        $entity = Entity::create(['name' => 'Организация для поиска', 'INN' => '1234567890']);
        foreach ([1, 2, 3] as $number) {
            $building = Building::create(['address' => 'Дом '.$number]);
            $floor = $building->floors()->create(['number' => $number]);
            $room = $building->apartments()->create(['number' => 'А-'.$number, 'building_floor_id' => $floor->id]);
            $room->entities()->attach($entity);
        }
        $building->apartments()->create(['number' => 'Не распределено']);
        $response = $this->getJson('/api/buildings?structure=1&per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('summary.buildings', 3)
            ->assertJsonPath('summary.floors', 3)->assertJsonPath('summary.apartments', 4)
            ->assertJsonPath('summary.entities', 1)->assertJsonPath('summary.without_floor', 1);
        $this->assertArrayHasKey('entities', $response->json('data.0.apartments.0'));
        $this->getJson('/api/buildings?structure=1&search='.urlencode('Организация для поиска'))->assertJsonPath('meta.total', 3);
        $this->getJson('/api/buildings?structure=1&search='.urlencode('А-2'))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/building-entity-options?search=1234567890')->assertOk()->assertJsonPath('0.id', $entity->id);
    }

    public function test_registry_paginates_newest_buildings_first_with_stable_order_for_equal_dates(): void
    {
        $new = Building::create(['address' => 'Ясная, 1']);
        $old = Building::create(['address' => 'Арбат, 2']);
        $sameDate = Building::create(['address' => 'Мира, 3']);
        $new->forceFill(['created_at' => '2026-10-14 12:00:00'])->save();
        $old->forceFill(['created_at' => '2025-01-01 12:00:00'])->save();
        $sameDate->forceFill(['created_at' => '2026-10-14 12:00:00'])->save();

        foreach ([$sameDate, $new, $old] as $index => $building) {
            $this->getJson('/api/buildings?structure=1&per_page=1&page='.($index + 1))
                ->assertOk()->assertJsonPath('data.0.id', $building->id)->assertJsonPath('meta.total', 3);
        }
    }

    public function test_migration_backfills_only_valid_explicit_room_links_without_inventing_floors(): void
    {
        $migration = require database_path('migrations/2026_10_14_120000_create_building_floors_and_apartment_entities.php');
        $migration->down();
        $building = Building::create(['address' => 'Миграция']);
        $other = Building::create(['address' => 'Другой']);
        $room = $building->apartments()->create(['number' => '10']);
        $entity = Entity::create(['name' => 'С помещением']);
        $without = Entity::create(['name' => 'Без помещения']);
        $entity->buildings()->attach($building->id, ['apartment_id' => $room->id]);
        $entity->buildings()->attach($building->id, ['apartment_id' => $room->id]);
        $without->buildings()->attach($building->id);
        $without->buildings()->attach($other->id, ['apartment_id' => $room->id]);
        $migration->up();
        $this->assertDatabaseCount('apartment_entity', 1);
        $this->assertDatabaseCount('building_floors', 0);
        $this->assertDatabaseHas('apartments', ['id' => $room->id, 'building_floor_id' => null]);
        $this->assertDatabaseCount('building_entities', 4);
    }

    public function test_reading_structure_never_enrolls_entities_and_customers_cannot_manage_rooms(): void
    {
        $building = Building::create(['address' => 'Приватное']);
        $room = $building->apartments()->create(['number' => '1']);
        $entity = Entity::create(['name' => 'Только связь здания']);
        $entity->buildings()->attach($building->id);
        DB::enableQueryLog();
        $this->getJson('/api/buildings?structure=1')->assertOk();
        $this->getJson("/api/buildings/{$building->id}/structure")->assertOk();
        $writes = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q['query']));
        DB::disableQueryLog();
        $this->assertCount(0, $writes);
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']));
        $this->getJson('/api/buildings?structure=1')->assertForbidden();
        $this->getJson('/api/building-entity-options')->assertForbidden();
        $this->getJson("/api/buildings/{$building->id}/structure")->assertForbidden();
        $this->postJson("/api/buildings/{$building->id}/floors", ['number' => 1])->assertForbidden();
        $this->postJson("/api/buildings/{$building->id}/apartments/{$room->id}/entities", ['entity_id' => $entity->id])->assertForbidden();
    }
}
