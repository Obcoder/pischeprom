<?php

namespace Tests\Feature;

use App\Jobs\EvaluateGoodStockAvailabilityJob;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Goods\GoodSaleStockSynchronizer;
use App\Services\Goods\GoodStockService;
use App\Services\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaleGoodsStockTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Entity $entity;

    private Good $good;

    private Measure $measure;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $user = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $user->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($user);

        $this->warehouse = Warehouse::query()->where('code', Warehouse::GOODS_CODE)->firstOrFail();
        $this->entity = Entity::query()->create(['name' => 'Покупатель']);
        $this->good = Good::query()->create(['name' => 'Какао-порошок']);
        $this->measure = Measure::query()->create(['name' => 'кг']);
    }

    public function test_sale_deducts_goods_stock_at_weighted_cost_and_updates_warehouse_balance(): void
    {
        $this->receipt(4, 10);
        $this->receipt(6, 20);

        $response = $this->postJson(route('sales.store'), $this->saleData([
            $this->line(3, 100),
        ]))->assertCreated()->assertJsonPath('data.total', 300);

        $saleId = $response->json('data.id');
        $pivotId = $response->json('data.goods.0.pivot.id');
        $this->assertDatabaseHas('good_stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'type' => GoodStockMovement::TYPE_WRITE_OFF,
            'quantity_delta' => -3,
            'unit_price' => 16,
            'source_type' => 'good_sale',
            'source_id' => $pivotId,
            'sale_id' => $saleId,
        ]);

        $this->getJson(route('good-warehouse-stock.index', [
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $this->good->id,
        ]))->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.quantity', 7)
            ->assertJsonPath('0.stock_value', 112);
    }

    public function test_repeated_sync_does_not_deduct_twice_or_reprice_existing_sale(): void
    {
        $this->receipt(10, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(3)]))
            ->assertCreated()->json('data.id');
        $movement = GoodStockMovement::query()->where('sale_id', $saleId)->sole();
        $this->receipt(10, 50);

        $service = app(GoodSaleStockSynchronizer::class);
        $service->sync(Sale::query()->findOrFail($saleId));
        $service->sync(Sale::query()->findOrFail($saleId));

        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movement->id,
            'quantity_delta' => -3,
            'unit_price' => 20,
        ]);
        $this->assertEqualsWithDelta(17, $this->balance(), 0.0000001);
    }

    public function test_overflowing_total_inventory_value_cannot_create_a_nonfinite_sale_cost(): void
    {
        $this->receipt(1, 1e308);
        $this->receipt(1, 1e308);

        $this->postJson(route('sales.store'), $this->saleData([$this->line(1)]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertEqualsWithDelta(2, $this->balance(), 0.0000001);
    }

    public function test_appending_the_same_good_creates_one_movement_per_sale_line(): void
    {
        $this->receipt(10, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(3)]))
            ->assertCreated()->json('data.id');

        $this->postJson(route('sales.goods.store', $saleId), $this->line(2, 30))
            ->assertCreated()->assertJsonPath('data.total', 360);

        $this->assertSame(2, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertSame(2, DB::table('good_sale')->where('sale_id', $saleId)->count());
        $this->assertEqualsWithDelta(5, $this->balance(), 0.0000001);
    }

    public function test_insufficient_stock_rolls_back_sale_and_every_line_and_movement(): void
    {
        $secondGood = Good::query()->create(['name' => 'Сахар']);
        $this->receipt(10, 20);
        $this->receipt(1, 5, ['good_id' => $secondGood->id]);
        Queue::fake();

        $this->postJson(route('sales.store'), $this->saleData([
            $this->line(2),
            $this->line(3, 100, ['good_id' => $secondGood->id]),
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertEqualsWithDelta(10, $this->balance(), 0.0000001);
        Queue::assertNotPushed(EvaluateGoodStockAvailabilityJob::class, fn ($job) => $job->goodId === $this->good->id);
    }

    public function test_duplicate_lines_cannot_each_spend_the_same_available_stock(): void
    {
        $this->receipt(5, 20);

        $this->postJson(route('sales.store'), $this->saleData([
            $this->line(3),
            $this->line(3),
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertEqualsWithDelta(5, $this->balance(), 0.0000001);
    }

    public function test_failed_attachment_preserves_sale_total_payment_state_and_stock(): void
    {
        $this->receipt(5, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(3)]))
            ->assertCreated()->json('data.id');

        $this->postJson(route('sales.goods.store', $saleId), $this->line(3))
            ->assertUnprocessable();

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total' => 300,
            'payment_status' => 'unpaid',
            'outstanding_amount' => 300,
        ]);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertEqualsWithDelta(2, $this->balance(), 0.0000001);
    }

    public function test_other_warehouses_and_measures_do_not_cover_goods_warehouse_shortage(): void
    {
        $otherWarehouse = Warehouse::query()->create(['name' => 'Другой склад', 'code' => 'other']);
        $otherMeasure = Measure::query()->create(['name' => 'мешок']);
        $this->receipt(1, 20);
        $this->receipt(100, 20, ['warehouse_id' => $otherWarehouse->id]);
        $this->receipt(100, 20, ['measure_id' => $otherMeasure->id]);
        $this->receipt(100, 20, ['measure_id' => null]);

        $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);
    }

    public function test_exact_available_fractional_quantity_can_be_sold_without_phantom_stock(): void
    {
        $this->receipt(0.1, 20);
        $this->receipt(0.2, 20);

        $this->postJson(route('sales.store'), $this->saleData([$this->line(0.3)]))->assertCreated();

        $this->assertEqualsWithDelta(0, $this->balance(), 0.0000001);
        $this->assertFalse(app(GoodStockService::class)->isInStock($this->good));
        $this->postJson(route('sales.store'), $this->saleData([$this->line(0.000001)]))
            ->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_cannot_create_a_sale(mixed $quantity): void
    {
        $this->receipt(10, 20);

        $this->postJson(route('sales.store'), $this->saleData([$this->line($quantity)]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertEqualsWithDelta(10, $this->balance(), 0.0000001);
    }

    public static function invalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'below precision' => [0.0000001],
            'overflow' => ['1e309'],
            'NaN' => ['NaN'],
        ];
    }

    public function test_legacy_goodsales_endpoint_deducts_stock_and_recalculates_sale_total(): void
    {
        $this->receipt(5, 20);
        $sale = $this->emptySale();

        $this->postJson(route('goodsales.store'), [
            'sale_id' => $sale->id,
            ...$this->line(2, 30),
        ])->assertCreated();

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 60, 'outstanding_amount' => 60]);
        $this->assertDatabaseHas('good_stock_movements', [
            'sale_id' => $sale->id,
            'source_type' => 'good_sale',
            'quantity_delta' => -2,
        ]);
        $this->assertEqualsWithDelta(3, $this->balance(), 0.0000001);
    }

    public function test_legacy_endpoint_cannot_bypass_insufficient_stock_check(): void
    {
        $this->receipt(1, 20);
        $sale = $this->emptySale();

        $this->postJson(route('goodsales.store'), [
            'sale_id' => $sale->id,
            'allow_negative_stock' => true,
            ...$this->line(2),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('good_sale', 0);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 0, 'outstanding_amount' => 0]);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);
    }

    public function test_sale_movement_cannot_be_edited_or_deleted_through_manual_movement_api(): void
    {
        $this->receipt(5, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))
            ->assertCreated()->json('data.id');
        $movement = GoodStockMovement::query()->where('sale_id', $saleId)->sole();

        $this->patchJson(route('good-stock-movements.update', $movement), $this->movementData(5))
            ->assertUnprocessable();
        $this->deleteJson(route('good-stock-movements.destroy', $movement))->assertUnprocessable();

        $this->assertDatabaseHas('good_stock_movements', ['id' => $movement->id, 'quantity_delta' => -2]);
        $this->assertEqualsWithDelta(3, $this->balance(), 0.0000001);
    }

    public function test_database_prevents_deleting_sale_that_still_has_stock_movements(): void
    {
        $this->receipt(5, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))
            ->assertCreated()->json('data.id');

        try {
            DB::table('sales')->where('id', $saleId)->delete();
            $this->fail('A sale with stock movements must not be deleted through a cascading foreign key.');
        } catch (QueryException $exception) {
            $this->assertStringContainsStringIgnoringCase('foreign key', $exception->getMessage());
        }

        $this->assertDatabaseHas('sales', ['id' => $saleId]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $saleId, 'quantity_delta' => -2]);
    }

    public function test_database_prevents_duplicate_movements_for_the_same_sale_line(): void
    {
        $this->receipt(5, 20);
        $saleId = $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))
            ->assertCreated()->json('data.id');
        $movement = GoodStockMovement::query()->where('sale_id', $saleId)->sole();

        try {
            $movement->replicate()->save();
            $this->fail('A sale line must have at most one stock movement.');
        } catch (QueryException $exception) {
            $this->assertStringContainsStringIgnoringCase('unique', $exception->getMessage());
        }

        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $saleId)->count());
        $this->assertEqualsWithDelta(3, $this->balance(), 0.0000001);
    }

    public function test_missing_goods_warehouse_rolls_back_sale_instead_of_using_another_warehouse(): void
    {
        $this->warehouse->delete();
        $other = Warehouse::query()->create(['name' => 'Другой склад']);
        $this->receipt(10, 20, ['warehouse_id' => $other->id]);

        $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_sale', 0);
        $this->assertDatabaseCount('good_stock_movements', 1);
    }

    public function test_manual_receipt_cannot_be_removed_or_reduced_below_sold_quantity(): void
    {
        $receipt = $this->receipt(5, 20);
        $this->postJson(route('sales.store'), $this->saleData([$this->line(4)]))->assertCreated();

        $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(3))
            ->assertUnprocessable();
        $this->deleteJson(route('good-stock-movements.destroy', $receipt))->assertUnprocessable();

        $this->assertDatabaseHas('good_stock_movements', ['id' => $receipt->id, 'quantity_delta' => 5]);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);

        $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(4))->assertOk();
        $this->assertEqualsWithDelta(0, $this->balance(), 0.0000001);
    }

    public function test_manual_write_off_cannot_bypass_available_stock_validation(): void
    {
        $this->receipt(1, 20);

        $this->postJson(route('good-stock-movements.store'), $this->movementData(2, [
            'type' => GoodStockMovement::TYPE_WRITE_OFF,
            'allow_negative_stock' => true,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);
    }

    public function test_purchase_cannot_be_reduced_or_deleted_after_its_stock_has_been_sold(): void
    {
        $service = app(PurchaseService::class);
        $purchase = $service->store($this->purchaseData(5));
        $this->postJson(route('sales.store'), $this->saleData([$this->line(4)]))->assertCreated();

        foreach (['reduce', 'delete'] as $operation) {
            try {
                if ($operation === 'reduce') {
                    $service->update($purchase, $this->purchaseData(3));
                } else {
                    $service->delete($purchase->fresh());
                }
                $this->fail('A purchase mutation must preserve quantities already sold.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'amount' => 100]);
        $this->assertDatabaseHas('good_purchase', ['purchase_id' => $purchase->id, 'quantity' => 5]);
        $this->assertDatabaseHas('good_stock_movements', ['purchase_id' => $purchase->id, 'quantity_delta' => 5]);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);

        $service->update($purchase->fresh(), $this->purchaseData(4));
        $this->assertEqualsWithDelta(0, $this->balance(), 0.0000001);
    }

    public function test_historical_negative_stock_may_be_improved_but_not_worsened_by_manual_edits(): void
    {
        $receipt = $this->receipt(5, 20);
        $this->receipt(-8, 20, ['type' => GoodStockMovement::TYPE_WRITE_OFF]);

        $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(6))->assertOk();
        $this->assertEqualsWithDelta(-2, $this->balance(), 0.0000001);

        $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(4))
            ->assertUnprocessable();
        $this->assertEqualsWithDelta(-2, $this->balance(), 0.0000001);
    }

    public function test_availability_job_is_dispatched_only_after_sale_transaction_commits(): void
    {
        $this->receipt(2, 20);
        Queue::fake();

        DB::transaction(function (): void {
            $this->postJson(route('sales.store'), $this->saleData([$this->line(2)]))->assertCreated();
            Queue::assertNotPushed(EvaluateGoodStockAvailabilityJob::class);
        });

        Queue::assertPushed(EvaluateGoodStockAvailabilityJob::class, fn ($job) => $job->goodId === $this->good->id);
        (new EvaluateGoodStockAvailabilityJob($this->good->id))->handle(app(GoodStockService::class));
        $this->assertDatabaseHas('good_stock_availabilities', ['good_id' => $this->good->id, 'is_in_stock' => false]);
        $this->assertDatabaseHas('good_seos', ['good_id' => $this->good->id, 'availability_status' => 'out_of_stock']);
    }

    public function test_moving_a_manual_receipt_to_another_good_refreshes_both_goods_after_commit(): void
    {
        $receipt = $this->receipt(5, 20);
        $otherGood = Good::query()->create(['name' => 'Другой товар']);
        Queue::fake();

        DB::transaction(function () use ($receipt, $otherGood): void {
            $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(5, [
                'good_id' => $otherGood->id,
            ]))->assertOk();
            Queue::assertNotPushed(EvaluateGoodStockAvailabilityJob::class);
        });

        Queue::assertPushed(EvaluateGoodStockAvailabilityJob::class, 2);
        Queue::assertPushed(EvaluateGoodStockAvailabilityJob::class, fn ($job) => $job->goodId === $this->good->id);
        Queue::assertPushed(EvaluateGoodStockAvailabilityJob::class, fn ($job) => $job->goodId === $otherGood->id);
        $this->assertEqualsWithDelta(0, $this->balance(), 0.0000001);
        $this->assertDatabaseHas('good_stock_movements', ['id' => $receipt->id, 'good_id' => $otherGood->id]);
    }

    public function test_rolled_back_manual_movement_does_not_dispatch_availability_jobs_for_either_good(): void
    {
        $receipt = $this->receipt(5, 20);
        $otherGood = Good::query()->create(['name' => 'Другой товар']);
        Queue::fake();

        try {
            DB::transaction(function () use ($receipt, $otherGood): void {
                $this->patchJson(route('good-stock-movements.update', $receipt), $this->movementData(5, [
                    'good_id' => $otherGood->id,
                ]))->assertOk();
                throw new RuntimeException('Roll back the outer transaction.');
            });
            $this->fail('The outer transaction should have been rolled back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Roll back the outer transaction.', $exception->getMessage());
        }

        Queue::assertNotPushed(EvaluateGoodStockAvailabilityJob::class);
        $this->assertDatabaseHas('good_stock_movements', ['id' => $receipt->id, 'good_id' => $this->good->id]);
        $this->assertEqualsWithDelta(5, $this->balance(), 0.0000001);
    }

    public function test_reconciliation_is_read_only_by_default(): void
    {
        $this->receipt(1, 20);
        $sale = $this->historicalSale(3);

        $this->artisan('goods-stock:reconcile-sales')
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('Отсутствующих списаний: 1')
            ->expectsOutputToContain('Позиций с дефицитом после сверки: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $sale->id]);
        $this->assertEqualsWithDelta(1, $this->balance(), 0.0000001);
    }

    public function test_reconciliation_applies_historical_shortage_once_and_updates_availability(): void
    {
        $this->receipt(1, 20);
        $sale = $this->historicalSale(3);

        $this->artisan('goods-stock:reconcile-sales', ['--apply' => true])->assertSuccessful();
        $movementId = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->id;
        $this->artisan('goods-stock:reconcile-sales', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movementId,
            'sale_id' => $sale->id,
            'source_type' => 'good_sale',
            'quantity_delta' => -3,
            'unit_price' => 20,
        ]);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertEqualsWithDelta(-2, $this->balance(), 0.0000001);
        $this->assertDatabaseHas('good_stock_availabilities', ['good_id' => $this->good->id, 'is_in_stock' => false]);
        $this->assertDatabaseHas('good_seos', ['good_id' => $this->good->id, 'availability_status' => 'out_of_stock']);
    }

    public function test_reconciliation_reports_even_the_smallest_supported_historical_shortage(): void
    {
        $this->historicalSale(0.000001);

        $this->artisan('goods-stock:reconcile-sales')
            ->expectsOutputToContain('Позиций с дефицитом после сверки: 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_reconciliation_preflight_rejects_invalid_historical_rows_before_any_writes(): void
    {
        $this->receipt(10, 20);
        $validSale = $this->historicalSale(2);
        $invalidSale = $this->historicalSale(0);

        $this->artisan('goods-stock:reconcile-sales', ['--apply' => true])
            ->expectsOutputToContain('некорректных позиций: 1')
            ->assertFailed();

        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $validSale->id]);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $invalidSale->id]);
        $this->assertEqualsWithDelta(10, $this->balance(), 0.0000001);
    }

    public function test_reconciliation_can_be_limited_to_selected_sales(): void
    {
        $this->receipt(10, 20);
        $selectedSale = $this->historicalSale(2);
        $otherSale = $this->historicalSale(3);

        $this->artisan('goods-stock:reconcile-sales', ['--apply' => true, '--sale' => [$selectedSale->id]])
            ->assertSuccessful();

        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $selectedSale->id, 'quantity_delta' => -2]);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $otherSale->id]);
        $this->assertEqualsWithDelta(8, $this->balance(), 0.0000001);
    }

    private function receipt(float $quantity, float $cost, array $overrides = []): GoodStockMovement
    {
        return GoodStockMovement::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => $quantity,
            'unit_price' => $cost,
            'moved_at' => '2026-08-23',
            ...$overrides,
        ]);
    }

    private function line(mixed $quantity, float $price = 100, array $overrides = []): array
    {
        return [
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'quantity' => $quantity,
            'price' => $price,
            ...$overrides,
        ];
    }

    private function saleData(array $lines): array
    {
        return ['date' => '2026-08-24', 'entity_id' => $this->entity->id, 'goods' => $lines];
    }

    private function emptySale(): Sale
    {
        return Sale::query()->create(['date' => '2026-08-24', 'entity_id' => $this->entity->id, 'total' => 0]);
    }

    private function historicalSale(float $quantity): Sale
    {
        $sale = $this->emptySale();
        $sale->goods()->attach($this->good->id, [
            'quantity' => $quantity,
            'measure_id' => $this->measure->id,
            'price' => 100,
        ]);

        return $sale;
    }

    private function balance(): float
    {
        return (float) GoodStockMovement::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('good_id', $this->good->id)
            ->where('measure_id', $this->measure->id)
            ->sum('quantity_delta');
    }

    private function movementData(float $quantity, array $overrides = []): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $this->good->id,
            'measure_id' => $this->measure->id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity' => $quantity,
            'unit_price' => 20,
            'moved_at' => '2026-08-23',
            ...$overrides,
        ];
    }

    private function purchaseData(float $quantity): array
    {
        return [
            'date' => '2026-08-23',
            'entity_id' => $this->entity->id,
            'items' => [[
                'good_id' => $this->good->id,
                'quantity' => $quantity,
                'measure_id' => $this->measure->id,
                'price' => 20,
                'currency_id' => null,
            ]],
        ];
    }
}
