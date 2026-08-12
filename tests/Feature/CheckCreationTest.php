<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\City;
use App\Models\Commodity;
use App\Models\Country;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\ExpenseArticle;
use App\Models\Measure;
use App\Models\Project;
use App\Models\Region;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_commodity_and_service_rows_are_created_together_without_overwriting_manual_amount(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);
        $commodity = Commodity::query()->create(['name' => 'Лецитин']);
        $service = Service::query()->create(['name' => 'Доставка']);
        $measure = Measure::query()->create(['name' => 'кг']);
        $warehouse = Warehouse::query()->where('code', 'main')->firstOrFail();

        $response = $this->postJson('/api/checks', [
            'date' => '2026-08-12',
            'entity_id' => $entity->id,
            'amount' => 1250.50,
            'commodities' => [
                [
                    'commodity_id' => $commodity->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 3,
                    'measure_id' => $measure->id,
                    'price' => 200,
                ],
            ],
            'services' => [
                [
                    'service_id' => $service->id,
                    'quantity' => 2,
                    'price' => 175,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('amount', 1250.50)
            ->assertJsonPath('commodity_items_total', 600)
            ->assertJsonPath('service_items_total', 350)
            ->assertJsonPath('positions_total', 950)
            ->assertJsonPath('items_count', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonCount(1, 'service_items');

        $checkId = $response->json('id');
        $itemId = $response->json('items.0.id');
        $serviceItemId = $response->json('service_items.0.id');

        $this->assertDatabaseHas('checks', [
            'id' => $checkId,
            'amount' => 1250.50,
        ]);
        $this->assertDatabaseHas('check_commodity', [
            'id' => $itemId,
            'check_id' => $checkId,
            'commodity_id' => $commodity->id,
            'quantity' => 3,
            'price' => 200,
        ]);
        $this->assertDatabaseHas('check_service', [
            'id' => $serviceItemId,
            'check_id' => $checkId,
            'service_id' => $service->id,
            'quantity' => 2,
            'price' => 175,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'source_type' => StockMovement::SOURCE_CHECK_COMMODITY,
            'source_id' => $itemId,
            'warehouse_id' => $warehouse->id,
            'commodity_id' => $commodity->id,
            'quantity_delta' => 3,
            'unit_price' => 200,
            'moved_at' => '2026-08-12 00:00:00',
        ]);
    }

    public function test_invalid_draft_row_does_not_create_a_check(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);

        $this->postJson('/api/checks', [
            'date' => '2026-08-12',
            'entity_id' => $entity->id,
            'amount' => 100,
            'commodities' => [
                [
                    'commodity_id' => 999999,
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
            'services' => [
                [
                    'service_id' => 999999,
                    'quantity' => 1,
                    'price' => 50,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'commodities.0.commodity_id',
                'services.0.service_id',
            ]);

        $this->assertDatabaseCount('checks', 0);
        $this->assertDatabaseCount('check_commodity', 0);
        $this->assertDatabaseCount('check_service', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_entity_options_include_attached_units(): void
    {
        $entity = Entity::query()->create(['name' => 'Юридическое лицо']);
        $unit = Unit::query()->create(['name' => 'Масложировой комбинат']);
        $entity->units()->attach($unit);

        $this->getJson('/api/entities?itemsPerPage=10&sortBy=name&sortDesc=false')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Юридическое лицо')
            ->assertJsonPath('data.0.units.0.name', 'Масложировой комбинат');
    }

    public function test_full_entity_can_be_created_for_check_with_requisites_and_relations(): void
    {
        $classification = EntityClassification::query()->create(['name' => 'ООО']);
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $region = Region::query()->create([
            'name' => 'Москва',
            'country_id' => $country->id,
        ]);
        $city = City::query()->create([
            'name' => 'Москва',
            'region_id' => $region->id,
        ]);
        $unit = Unit::query()->create(['name' => 'Основная площадка']);

        $entityId = $this->postJson('/api/entities', [
            'name' => 'Ромашка',
            'full_name' => 'Общество с ограниченной ответственностью «Ромашка»',
            'entity_classification_id' => $classification->id,
            'INN' => '7707083893',
            'KPP' => '770701001',
            'OGRN' => '1027700132195',
            'legal_address' => '127006, г. Москва, ул. Долгоруковская, д. 1',
            'country_id' => $country->id,
            'dadata_raw' => [
                'source' => 'dadata_suggest',
                'data' => ['inn' => '7707083893'],
            ],
            'cities' => [$city->id],
            'units' => [$unit->id],
            'buildings' => [],
            'emails' => [],
            'telephones' => [],
            'chats' => [],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ромашка')
            ->assertJsonPath('data.INN', '7707083893')
            ->assertJsonPath('data.KPP', '770701001')
            ->assertJsonPath('data.OGRN', '1027700132195')
            ->assertJsonPath('data.classification.name', 'ООО')
            ->assertJsonPath('data.country.name', 'Россия')
            ->assertJsonPath('data.cities.0.name', 'Москва')
            ->assertJsonPath('data.units.0.name', 'Основная площадка')
            ->json('data.id');

        $this->postJson('/api/checks', [
            'date' => '2026-08-12',
            'entity_id' => $entityId,
            'amount' => 1500,
        ])->assertCreated()
            ->assertJsonPath('entity.id', $entityId)
            ->assertJsonPath('entity.units.0.name', 'Основная площадка');

        $this->assertDatabaseHas('entities', [
            'id' => $entityId,
            'INN' => '7707083893',
            'KPP' => '770701001',
            'OGRN' => '1027700132195',
            'country_id' => $country->id,
        ]);
        $this->assertDatabaseHas('city_entity', [
            'entity_id' => $entityId,
            'city_id' => $city->id,
        ]);
        $this->assertDatabaseHas('entity_unit', [
            'entity_id' => $entityId,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_check_index_includes_entity_units_and_compact_row_summaries(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);
        $unit = Unit::query()->create(['name' => 'Производственная площадка']);
        $entity->units()->attach($unit);
        $article = ExpenseArticle::query()->create([
            'name' => 'Закупки',
            'color' => '#7aa35b',
        ]);
        $project = Project::query()->create(['name' => 'Доставка']);
        $commodity = Commodity::query()->create([
            'name' => 'Сырьё',
            'expense_article_id' => $article->id,
        ]);
        $service = Service::query()->create([
            'name' => 'Перевозка',
            'expense_article_id' => $article->id,
            'project_id' => $project->id,
        ]);

        $this->postJson('/api/checks', [
            'date' => '2026-08-12',
            'entity_id' => $entity->id,
            'amount' => 500,
            'commodities' => [[
                'commodity_id' => $commodity->id,
                'quantity' => 2,
                'price' => 100,
            ]],
            'services' => [[
                'service_id' => $service->id,
                'quantity' => 1,
                'price' => 50,
            ]],
        ])->assertCreated();

        $this->getJson('/api/checks')
            ->assertOk()
            ->assertJsonPath('data.0.entity.units.0.id', $unit->id)
            ->assertJsonPath('data.0.entity.units.0.name', 'Производственная площадка')
            ->assertJsonPath('data.0.table_summary.expense_articles.0.name', 'Закупки')
            ->assertJsonPath('data.0.table_summary.expense_articles.0.color', '#7aa35b')
            ->assertJsonPath('data.0.table_summary.projects.0.name', 'Без проекта')
            ->assertJsonPath('data.0.table_summary.projects.1.name', 'Доставка')
            ->assertJsonPath('meta.without_project_total', 200);
    }

    public function test_existing_commodity_endpoints_keep_stock_movement_in_sync(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);
        $commodity = Commodity::query()->create(['name' => 'Масло']);
        $warehouse = Warehouse::query()->where('code', 'main')->firstOrFail();
        $check = Check::query()->create([
            'date' => '2026-08-12',
            'entity_id' => $entity->id,
            'amount' => 500,
        ]);

        $itemId = $this->postJson("/api/checks/{$check->id}/commodities", [
            'commodity_id' => $commodity->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
            'price' => 50,
        ])->assertCreated()->json('id');

        $this->patchJson("/api/check-commodities/{$itemId}", [
            'quantity' => 4,
            'price' => 75,
        ])->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'source_type' => StockMovement::SOURCE_CHECK_COMMODITY,
            'source_id' => $itemId,
            'quantity_delta' => 4,
            'unit_price' => 75,
        ]);

        $this->deleteJson("/api/check-commodities/{$itemId}")->assertNoContent();

        $this->assertDatabaseMissing('check_commodity', ['id' => $itemId]);
        $this->assertDatabaseMissing('stock_movements', [
            'source_type' => StockMovement::SOURCE_CHECK_COMMODITY,
            'source_id' => $itemId,
        ]);
    }

    public function test_existing_service_endpoints_continue_to_manage_service_rows(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);
        $article = ExpenseArticle::query()->create(['name' => 'Логистика']);
        $service = Service::query()->create([
            'name' => 'Доставка',
            'expense_article_id' => $article->id,
        ]);
        $check = Check::query()->create([
            'date' => '2026-08-12',
            'entity_id' => $entity->id,
            'amount' => 900,
        ]);

        $itemId = $this->postJson("/api/checks/{$check->id}/services", [
            'service_id' => $service->id,
            'quantity' => 1,
            'price' => 500,
        ])->assertCreated()
            ->assertJsonPath('expense_article_id', $article->id)
            ->json('id');

        $this->patchJson("/api/check-services/{$itemId}", [
            'quantity' => 2,
            'price' => 450,
        ])->assertOk()
            ->assertJsonPath('data.total_price', 900);

        $this->assertDatabaseHas('check_service', [
            'id' => $itemId,
            'check_id' => $check->id,
            'service_id' => $service->id,
            'expense_article_id' => $article->id,
            'quantity' => 2,
            'price' => 450,
        ]);

        $this->deleteJson("/api/check-services/{$itemId}")->assertNoContent();

        $this->assertDatabaseMissing('check_service', ['id' => $itemId]);
    }
}
