<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\Commodity;
use App\Models\Entity;
use App\Models\ExpenseArticle;
use App\Models\Measure;
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
