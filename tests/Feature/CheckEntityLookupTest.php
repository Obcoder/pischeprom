<?php

namespace Tests\Feature;

use App\Models\Commodity;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckEntityLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_entity_is_searchable_and_every_option_is_reachable_beyond_the_first_thousand_records(): void
    {
        $this->signInEmployee();
        $mitex = Entity::query()->forceCreate(['name' => 'Митекс', 'created_at' => now()->subYear()]);
        Entity::query()->insert(array_map(fn (int $index) => [
            'name' => sprintf('Альфа %04d', $index),
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, 1001)));

        $this->getJson(route('checks.entities.index', ['search' => '  мИтЕкС  ']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mitex->id)
            ->assertJsonPath('meta.total', 1);

        $ids = [];
        for ($page = 1; $page <= 11; $page++) {
            $response = $this->getJson(route('checks.entities.index', ['page' => $page, 'per_page' => 100]))
                ->assertOk()
                ->assertJsonPath('meta.current_page', $page)
                ->assertJsonPath('meta.last_page', 11)
                ->assertJsonPath('meta.per_page', 100)
                ->assertJsonPath('meta.total', 1002);
            array_push($ids, ...array_column($response->json('data'), 'id'));
        }

        $this->assertCount(1002, array_unique($ids));
        $this->assertSame($mitex->id, end($ids));
    }

    public function test_search_covers_full_name_requisites_and_attached_units(): void
    {
        $this->signInEmployee();
        $entity = Entity::query()->create([
            'name' => 'Митекс',
            'full_name' => 'Торговая компания «Митекс»',
            'INN' => '7707083893',
            'KPP' => '770701001',
            'OGRN' => '1027700132195',
        ]);
        $unit = Unit::query()->create(['name' => 'Кондитерская фабрика']);
        $entity->units()->attach($unit);
        Entity::query()->create(['name' => 'Посторонний контрагент']);

        foreach (['ТОРГОВАЯ КОМПАНИЯ', '7707083893', '770701001', '1027700132195', '  кОнДиТеРсКаЯ  '] as $search) {
            $this->getJson(route('checks.entities.index', ['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $entity->id)
                ->assertJsonPath('data.0.units.0.name', $unit->name);
        }
    }

    public function test_check_commodity_options_are_complete_across_pages_with_repeated_names(): void
    {
        $this->signInEmployee();
        Commodity::query()->insert(array_fill(0, 502, [
            'name' => 'Одинаковое сырьё',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $ids = [];
        foreach ([1 => 500, 2 => 2] as $page => $expectedCount) {
            $response = $this->getJson(route('commodities.index', [
                'page' => $page,
                'per_page' => 500,
                'sort_by' => 'name',
                'sort_desc' => 'false',
            ]))->assertOk()
                ->assertJsonCount($expectedCount, 'data')
                ->assertJsonPath('meta.last_page', 2)
                ->assertJsonPath('meta.total', 502);
            array_push($ids, ...array_column($response->json('data'), 'id'));
        }

        $this->assertSame(Commodity::query()->orderBy('id')->pluck('id')->all(), $ids);
    }

    public function test_search_treats_sql_wildcards_as_literal_characters(): void
    {
        $this->signInEmployee();
        $entity = Entity::query()->create(['name' => 'Компания 100%_!']);
        Entity::query()->create(['name' => 'Компания 100500']);

        foreach (['%', '_', '!', '100%_!'] as $search) {
            $this->getJson(route('checks.entities.index', ['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $entity->id);
        }
    }

    public function test_selected_ids_are_hydrated_independently_of_search_and_payload_stays_compact(): void
    {
        $this->signInEmployee();
        $classification = EntityClassification::query()->create(['name' => 'ООО']);
        $entity = Entity::query()->create([
            'name' => 'Митекс',
            'entity_classification_id' => $classification->id,
            'bank_account_number' => '40702810000000000001',
            'legal_address' => 'Адрес контрагента',
            'dadata_raw' => ['private' => 'details'],
        ]);
        $unit = Unit::query()->create(['name' => 'Фабрика']);
        $entity->units()->attach($unit);
        Entity::query()->create(['name' => 'Другой контрагент']);

        $this->getJson(route('checks.entities.index', ['ids' => [$entity->id], 'search' => 'Не совпадает']))
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $entity->id,
                    'name' => 'Митекс',
                    'full_name' => null,
                    'INN' => null,
                    'KPP' => null,
                    'OGRN' => null,
                    'units' => [['id' => $unit->id, 'name' => 'Фабрика']],
                    'classification' => ['id' => $classification->id, 'name' => 'ООО'],
                ]],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 1],
            ]);

        $this->getJson(route('checks.entities.index', ['ids' => [999999]]))
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_equal_names_have_stable_pagination_and_missing_classification_is_null(): void
    {
        $this->signInEmployee();
        $first = Entity::query()->create(['name' => 'Одинаковое название']);
        $second = Entity::query()->create(['name' => 'Одинаковое название']);

        foreach ([$first, $second] as $index => $entity) {
            $this->getJson(route('checks.entities.index', ['page' => $index + 1, 'per_page' => 1]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $entity->id)
                ->assertJsonPath('data.0.classification', null)
                ->assertJsonPath('meta.last_page', 2);
        }
    }

    public function test_lookup_rejects_invalid_or_unbounded_parameters(): void
    {
        $this->signInEmployee();

        foreach ([
            ['search' => str_repeat('я', 256)],
            ['page' => 0],
            ['per_page' => 0],
            ['per_page' => 101],
            ['ids' => range(1, 21)],
            ['ids' => '1'],
        ] as $parameters) {
            $this->getJson(route('checks.entities.index', $parameters))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($parameters));
        }

        $this->getJson(route('checks.entities.index', ['ids' => ['bad']]))
            ->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    }

    public function test_lookup_requires_authenticated_staff(): void
    {
        $this->getJson(route('checks.entities.index'))->assertUnauthorized();

        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->getJson(route('checks.entities.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'blocked']))
            ->getJson(route('checks.entities.index'))->assertForbidden();
    }

    private function signInEmployee(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }
}
