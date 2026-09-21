<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaleDateUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);
        $this->entity = Entity::query()->create(['name' => 'Покупатель']);
    }

    public function test_date_correction_updates_existing_stock_dates_and_preserves_sale_and_payment_amounts(): void
    {
        $good = Good::query()->create(['name' => 'Сахар']);
        $measure = Measure::query()->create(['name' => 'кг']);
        GoodStockMovement::query()->create([
            'warehouse_id' => Warehouse::query()->where('code', Warehouse::GOODS_CODE)->sole()->id,
            'good_id' => $good->id,
            'measure_id' => $measure->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => 10,
            'unit_price' => 20,
            'moved_at' => '2026-08-20',
        ]);
        $saleId = $this->postJson('/api/sales', [
            'date' => '2026-09-11',
            'entity_id' => $this->entity->id,
            'goods' => [[
                'good_id' => $good->id,
                'measure_id' => $measure->id,
                'quantity' => 3,
                'price' => 100,
            ]],
        ])->assertCreated()->json('data.id');
        $sale = Sale::query()->findOrFail($saleId);
        $sale->forceFill([
            'payment_status' => 'partial',
            'paid_amount' => '100.00',
            'outstanding_amount' => '200.00',
        ])->save();
        $previous = $this->sale('2026-08-25');
        $this->sale('2026-09-01');
        $before = $sale->fresh()->getAttributes();
        $lineBefore = DB::table('good_sale')->where('sale_id', $saleId)->sole();
        $movement = GoodStockMovement::query()->where('sale_id', $saleId)->sole();

        $this->patchJson("/api/sales/{$saleId}", [
            'date' => '2026-08-30',
            'entity_id' => Entity::query()->create(['name' => 'Другой покупатель'])->id,
            'total' => 1,
            'goods' => [],
            'paid_amount' => 0,
        ])->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.date', '2026-08-30')
            ->assertJsonPath('data.month', '2026-08')
            ->assertJsonPath('data.total', 300)
            ->assertJsonPath('data.entity.id', $this->entity->id)
            ->assertJsonPath('data.goods.0.pivot.id', $lineBefore->id)
            ->assertJsonPath('data.previous_sale.id', $previous->id)
            ->assertJsonPath('data.previous_sale.days', 5);

        $sale->refresh();
        foreach (['entity_id', 'total', 'payment_status', 'paid_amount', 'outstanding_amount', 'overpaid_amount'] as $field) {
            $this->assertSame($before[$field], $sale->getAttributes()[$field]);
        }
        $this->assertEquals($lineBefore, DB::table('good_sale')->where('sale_id', $saleId)->sole());
        $this->assertSame($movement->id, GoodStockMovement::query()->where('sale_id', $saleId)->sole()->id);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertSame('2026-08-30', $movement->fresh()->moved_at->toDateString());
        $this->assertSame($movement->quantity_delta, $movement->fresh()->quantity_delta);
        $this->assertSame($movement->unit_price, $movement->fresh()->unit_price);
        $this->assertEquals(7, GoodStockMovement::query()->where('good_id', $good->id)->sum('quantity_delta'));
    }

    public function test_date_correction_does_not_create_stock_movements_for_historical_sale_lines(): void
    {
        $sale = $this->sale();
        $sale->goods()->attach(Good::query()->create(['name' => 'Исторический товар'])->id, [
            'measure_id' => Measure::query()->create(['name' => 'кг'])->id,
            'quantity' => 3,
            'price' => 100,
        ]);

        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-08-30'])
            ->assertOk()->assertJsonPath('data.date', '2026-08-30');

        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertSame('300.00', $sale->fresh()->total);
    }

    public function test_date_is_required_and_must_be_a_valid_iso_calendar_date(): void
    {
        $sale = $this->sale();

        foreach ([[], ['date' => null], ['date' => ''], ['date' => '2026-02-30'], ['date' => '30.08.2026']] as $payload) {
            $this->patchJson("/api/sales/{$sale->id}", $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('date');
        }

        $this->assertSame('2026-09-11', $sale->fresh()->date->toDateString());
    }

    public function test_employee_without_warehouse_permission_cannot_change_sale_date(): void
    {
        $sale = $this->sale();
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));

        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-08-30'])->assertForbidden();

        $this->assertSame('2026-09-11', $sale->fresh()->date->toDateString());
    }

    private function sale(string $date = '2026-09-11'): Sale
    {
        return Sale::query()->create([
            'date' => $date,
            'entity_id' => $this->entity->id,
            'total' => '300.00',
        ]);
    }
}
