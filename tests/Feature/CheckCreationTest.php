<?php

namespace Tests\Feature;

use App\Models\Check;
use App\Models\Commodity;
use App\Models\Entity;
use App\Models\Measure;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_and_commodity_rows_are_created_together_without_overwriting_manual_amount(): void
    {
        $entity = Entity::query()->create(['name' => 'Поставщик']);
        $commodity = Commodity::query()->create(['name' => 'Лецитин']);
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
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('amount', 1250.50)
            ->assertJsonPath('commodity_items_total', 600)
            ->assertJsonCount(1, 'items');

        $checkId = $response->json('id');
        $itemId = $response->json('items.0.id');

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
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('commodities.0.commodity_id');

        $this->assertDatabaseCount('checks', 0);
        $this->assertDatabaseCount('check_commodity', 0);
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
}
