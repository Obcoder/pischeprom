<?php

namespace Tests\Feature;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Services\PaymentAllocationService;
use App\Events\CommerceDataChanged;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\BankTransactionAllocation;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodStockMovement;
use App\Models\Measure;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Entity $entity;

    private Good $good;

    private Measure $measure;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->admin = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $this->admin->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($this->admin);
        $this->entity = Entity::query()->create(['name' => 'Покупатель']);
        $this->measure = Measure::query()->firstOrCreate(['name' => 'кг']);
        $this->good = Good::query()->create(['name' => 'Какао-порошок', 'measure_id' => $this->measure->id]);
        $this->warehouse = Warehouse::query()->where('code', Warehouse::GOODS_CODE)->sole();
    }

    public function test_admin_can_create_read_and_edit_sale_and_all_its_lines_without_warehouse_permission(): void
    {
        $removedGood = Good::query()->create(['name' => 'Сахар']);
        $addedGood = Good::query()->create(['name' => 'Соль']);
        $this->receipt($this->good, 4, 10);
        $this->receipt($this->good, 6, 20);
        $this->receipt($removedGood, 8, 5);
        $this->receipt($addedGood, 7, 12);
        $sale = $this->createSale([
            $this->line($this->good, 3, 100),
            $this->line($removedGood, 2, 20),
        ]);
        $originalLines = DB::table('good_sale')->where('sale_id', $sale->id)->orderBy('id')->get();
        $retainedId = $originalLines[0]->id;
        $retainedMovement = GoodStockMovement::query()->where('source_id', $retainedId)
            ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)->sole();
        $this->receipt($this->good, 10, 50);
        $newEntity = Entity::query()->create(['name' => 'Другой покупатель']);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-01',
            'entity_id' => $newEntity->id,
            'payment_reference' => 'INV-EDITED',
            'goods' => [
                ['id' => $retainedId, ...$this->line($this->good, 5, 120)],
                $this->line($addedGood, 1.5, 40),
            ],
        ])->assertOk()
            ->assertJsonPath('data.date', '2026-10-01')
            ->assertJsonPath('data.entity.id', $newEntity->id)
            ->assertJsonPath('data.payment_reference', 'INV-EDITED')
            ->assertJsonPath('data.total', 660)
            ->assertJsonCount(2, 'data.goods');

        $this->getJson("/api/sales/{$sale->id}")->assertOk()
            ->assertJsonPath('id', $sale->id)
            ->assertJsonPath('total', 660)
            ->assertJsonCount(2, 'goods');
        $this->assertDatabaseHas('good_sale', [
            'id' => $retainedId,
            'sale_id' => $sale->id,
            'good_id' => $this->good->id,
            'quantity' => 5,
            'price' => 120,
            'total' => 600,
        ]);
        $this->assertDatabaseMissing('good_sale', ['id' => $originalLines[1]->id]);
        $this->assertDatabaseMissing('good_stock_movements', [
            'source_type' => GoodStockMovement::SOURCE_GOOD_SALE,
            'source_id' => $originalLines[1]->id,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $retainedMovement->id,
            'source_id' => $retainedId,
            'quantity_delta' => -5,
            'unit_price' => 16,
        ]);
        $this->assertSame('2026-10-01', $retainedMovement->fresh()->moved_at->toDateString());
        $this->assertDatabaseHas('good_stock_movements', [
            'sale_id' => $sale->id,
            'good_id' => $addedGood->id,
            'quantity_delta' => -1.5,
            'unit_price' => 12,
        ]);
        $this->assertSame(2, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertEqualsWithDelta(15, $this->balance($this->good), 0.000001);
        $this->assertEqualsWithDelta(8, $this->balance($removedGood), 0.000001);
        $this->assertEqualsWithDelta(5.5, $this->balance($addedGood), 0.000001);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'outstanding_amount' => 660, 'paid_amount' => 0]);
    }

    public function test_replacing_a_line_good_and_measure_restores_old_stock_and_uses_new_good_cost(): void
    {
        $pieces = Measure::query()->create(['name' => 'шт']);
        $replacement = Good::query()->create(['name' => 'Коробка', 'measure_id' => $pieces->id]);
        $this->receipt($this->good, 10, 20);
        $this->receipt($replacement, 6, 35);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $pivotId = $sale->goods->sole()->pivot->id;
        $movementId = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->id;

        $payload = [
            'date' => '2026-10-02',
            'goods' => [['id' => $pivotId, ...$this->line($replacement, 2, 80)]],
        ];
        $this->putJson("/api/sales/{$sale->id}", $payload)->assertOk()->assertJsonPath('data.total', 160);
        $this->putJson("/api/sales/{$sale->id}", $payload)->assertOk();

        $this->assertDatabaseHas('good_sale', [
            'id' => $pivotId,
            'good_id' => $replacement->id,
            'measure_id' => $pieces->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movementId,
            'good_id' => $replacement->id,
            'measure_id' => $pieces->id,
            'quantity_delta' => -2,
            'unit_price' => 35,
        ]);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertEqualsWithDelta(10, $this->balance($this->good), 0.000001);
        $this->assertEqualsWithDelta(4, $this->balance($replacement), 0.000001);
    }

    public function test_removing_all_lines_restores_stock_and_allows_explicit_sale_total(): void
    {
        $this->receipt($this->good, 10, 20);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'goods' => [],
            'total' => 75.5,
        ])->assertOk()->assertJsonPath('data.total', 75.5)->assertJsonCount(0, 'data.goods');

        $this->assertDatabaseMissing('good_sale', ['sale_id' => $sale->id]);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $sale->id]);
        $this->assertEqualsWithDelta(10, $this->balance($this->good), 0.000001);
        $this->assertSame('75.50', $sale->fresh()->outstanding_amount);
    }

    public function test_metadata_edit_preserves_historical_lines_without_creating_missing_stock_movements(): void
    {
        $sale = Sale::query()->create(['date' => '2026-09-10', 'entity_id' => $this->entity->id, 'total' => 300]);
        $sale->goods()->attach($this->good->id, ['measure_id' => $this->measure->id, 'quantity' => 3, 'price' => 100]);
        $lineBefore = DB::table('good_sale')->where('sale_id', $sale->id)->sole();

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'payment_reference' => 'HISTORICAL',
            'total' => 250,
        ])->assertOk()->assertJsonPath('data.total', 250);

        $this->assertEquals($lineBefore, DB::table('good_sale')->where('sale_id', $sale->id)->sole());
        $this->assertDatabaseCount('good_stock_movements', 0);
        $this->assertSame('250.00', $sale->fresh()->outstanding_amount);
    }

    public function test_total_corrections_recalculate_payments_without_modifying_bank_allocations(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $allocation = $this->allocate($sale, '100.00');
        $allocationBefore = $allocation->getAttributes();

        foreach ([
            [80, 'overpaid', '0.00', '20.00'],
            [100, 'paid', '0.00', '0.00'],
            [240, 'partially_paid', '140.00', '0.00'],
        ] as [$total, $status, $outstanding, $overpaid]) {
            $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', 'total' => $total])
                ->assertOk()->assertJsonPath('data.total', $total);
            $sale->refresh();
            $this->assertSame($status, $sale->payment_status);
            $this->assertSame('100.00', $sale->paid_amount);
            $this->assertSame($outstanding, $sale->outstanding_amount);
            $this->assertSame($overpaid, $sale->overpaid_amount);
        }

        $this->assertSame($allocationBefore, $allocation->fresh()->getAttributes());
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
    }

    public function test_price_correction_preserves_historical_unit_and_does_not_post_missing_stock(): void
    {
        $sale = Sale::query()->create(['date' => '2026-09-10', 'entity_id' => $this->entity->id, 'total' => 300]);
        $sale->goods()->attach($this->good->id, ['measure_id' => $this->measure->id, 'quantity' => 3, 'price' => 100]);
        $lineId = $sale->goods->sole()->pivot->id;
        $newMeasure = Measure::query()->create(['name' => 'мешок']);
        DB::table('goods')->where('id', $this->good->id)->update(['measure_id' => $newMeasure->id]);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineId, ...$this->line($this->good, 3, 120)]],
        ])->assertOk()->assertJsonPath('data.total', 360);

        $this->assertDatabaseHas('good_sale', ['id' => $lineId, 'measure_id' => $this->measure->id, 'quantity' => 3, 'price' => 120]);
        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_admin_can_correct_a_historical_unit_to_the_current_product_unit_and_sync_stock(): void
    {
        $this->receipt($this->good, 10, 20);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $movementId = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->id;
        $pieces = Measure::query()->create(['name' => 'шт']);
        DB::table('goods')->where('id', $this->good->id)->update(['measure_id' => $pieces->id]);
        $this->good->refresh();
        $productBefore = $this->good->fresh()->getAttributes();
        $this->receipt($this->good, 6, 35);
        $payload = [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineId, ...$this->line($this->good, 2, 80)]],
        ];

        $this->patchJson("/api/sales/{$sale->id}", $payload)->assertOk()
            ->assertJsonPath('data.goods.0.pivot.measure_id', $pieces->id)
            ->assertJsonPath('data.total', 160);
        $this->patchJson("/api/sales/{$sale->id}", $payload)->assertOk();

        $this->assertDatabaseHas('good_sale', ['id' => $lineId, 'measure_id' => $pieces->id, 'quantity' => 2, 'price' => 80]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movementId,
            'source_id' => $lineId,
            'measure_id' => $pieces->id,
            'quantity_delta' => -2,
            'unit_price' => 35,
        ]);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertEqualsWithDelta(10, GoodStockMovement::query()->where('good_id', $this->good->id)
            ->where('measure_id', $this->measure->id)->sum('quantity_delta'), 0.000001);
        $this->assertEqualsWithDelta(4, $this->balance($this->good), 0.000001);
        $this->assertSame($productBefore, $this->good->fresh()->getAttributes());
    }

    public function test_sale_unit_correction_rejects_a_unit_that_differs_from_the_product_unit(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineBefore = DB::table('good_sale')->where('sale_id', $sale->id)->sole();
        $movementBefore = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes();
        $productBefore = $this->good->fresh()->getAttributes();
        $pieces = Measure::query()->create(['name' => 'шт']);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineBefore->id, ...$this->line($this->good, 3, 120), 'measure_id' => $pieces->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertEquals($lineBefore, DB::table('good_sale')->where('sale_id', $sale->id)->sole());
        $this->assertSame($movementBefore, GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes());
        $this->assertSame($productBefore, $this->good->fresh()->getAttributes());
        $this->assertSame('300.00', $sale->fresh()->total);
    }

    #[DataProvider('unconfiguredSaleGoods')]
    public function test_admin_can_set_a_sale_unit_for_an_unconfigured_good_without_changing_its_card(bool $replaceGood): void
    {
        $this->receipt($this->good, 10, 20);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $movementId = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->id;
        $good = $replaceGood
            ? Good::query()->create(['name' => 'Товар без единицы', 'measure_id' => null])
            : $this->good;
        DB::table('goods')->where('id', $good->id)->update(['measure_id' => null]);
        $productBefore = $good->fresh()->getAttributes();
        $pieces = Measure::query()->create(['name' => 'шт']);
        $payload = [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineId, 'good_id' => $good->id, 'measure_id' => $pieces->id, 'quantity' => 2, 'price' => 120]],
        ];

        $this->patchJson("/api/sales/{$sale->id}", $payload)->assertOk()
            ->assertJsonPath('data.goods.0.pivot.measure_id', $pieces->id)
            ->assertJsonPath('data.goods.0.measure_id', null)
            ->assertJsonPath('data.total', 240);
        $this->patchJson("/api/sales/{$sale->id}", $payload)->assertOk();

        $this->assertDatabaseHas('good_sale', ['id' => $lineId, 'good_id' => $good->id, 'measure_id' => $pieces->id, 'quantity' => 2, 'price' => 120]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movementId,
            'source_id' => $lineId,
            'good_id' => $good->id,
            'measure_id' => $pieces->id,
            'quantity_delta' => -2,
        ]);
        $this->assertSame(1, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertEqualsWithDelta(10, $this->balance($this->good), 0.000001);
        $this->assertSame($productBefore, $good->fresh()->getAttributes());
    }

    public static function unconfiguredSaleGoods(): array
    {
        return [
            'replace with unconfigured good' => [true],
            'correct existing unconfigured good' => [false],
        ];
    }

    public function test_admin_can_add_an_unconfigured_good_with_an_explicit_unit_while_editing_a_sale(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $good = Good::query()->create(['name' => 'Товар без единицы', 'measure_id' => null]);
        $productBefore = $good->fresh()->getAttributes();
        $pieces = Measure::query()->create(['name' => 'шт']);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'goods' => [
                ['id' => $lineId, ...$this->line($this->good, 3, 100)],
                [...$this->line($good, 2, 120), 'measure_id' => $pieces->id],
            ],
        ])->assertOk()->assertJsonPath('data.total', 540)->assertJsonCount(2, 'data.goods');

        $this->assertDatabaseHas('good_sale', ['sale_id' => $sale->id, 'good_id' => $good->id, 'measure_id' => $pieces->id, 'quantity' => 2]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $sale->id, 'good_id' => $good->id, 'measure_id' => $pieces->id, 'quantity_delta' => -2]);
        $this->assertSame(2, GoodStockMovement::query()->where('sale_id', $sale->id)->count());
        $this->assertSame($productBefore, $good->fresh()->getAttributes());
    }

    public function test_admin_must_select_a_sale_unit_when_replacing_a_line_with_an_unconfigured_good(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $good = Good::query()->create(['name' => 'Товар без единицы', 'measure_id' => null]);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineId, ...$this->line($good, 3, 120)]],
        ])->assertUnprocessable()->assertJsonValidationErrors('goods')
            ->assertJsonPath('errors.goods.0', "Выберите единицу измерения товара «{$good->name}» в продаже.");

        $this->assertDatabaseHas('good_sale', ['id' => $lineId, 'good_id' => $this->good->id, 'measure_id' => $this->measure->id, 'price' => 100]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $sale->id, 'good_id' => $this->good->id, 'quantity_delta' => -3]);
        $this->assertNull($good->fresh()->measure_id);
    }

    public function test_sale_creation_and_appending_still_require_a_configured_product_unit(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $good = Good::query()->create(['name' => 'Товар без единицы', 'measure_id' => null]);
        $line = [...$this->line($good, 2, 120), 'measure_id' => $this->measure->id];

        $this->postJson('/api/sales', [
            'date' => '2026-10-02',
            'entity_id' => $this->entity->id,
            'goods' => [$line],
        ])->assertUnprocessable()->assertJsonValidationErrors('goods');
        $this->postJson("/api/sales/{$sale->id}/goods", $line)
            ->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('good_sale', 1);
        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertNull($good->fresh()->measure_id);
    }

    public function test_price_only_correction_publishes_change_even_when_sale_total_and_timestamp_are_unchanged(): void
    {
        $this->freezeTime();
        config()->set(['realtime.enabled' => true, 'realtime.queue_connection' => 'database']);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $movementBefore = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes();
        Event::fake([CommerceDataChanged::class]);

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-09-10',
            'total' => 300,
            'goods' => [['id' => $lineId, ...$this->line($this->good, 3, 120)]],
        ])->assertOk()->assertJsonPath('data.total', 300);

        $this->assertSame($movementBefore, GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes());
        Event::assertDispatched(CommerceDataChanged::class, fn (CommerceDataChanged $event) => in_array('sales', $event->topics, true));
    }

    public function test_warehouse_employee_can_correct_date_but_cannot_edit_or_delete_sale(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $employee = User::factory()->create(['type' => 'employee', 'status' => 'active']);
        $employee->givePermissionTo(Permission::findOrCreate('warehouse.move', 'crm'));
        $this->actingAs($employee);

        foreach ([
            ['total' => 1],
            ['entity_id' => $this->entity->id],
            ['payment_reference' => 'UNAUTHORIZED'],
            ['goods' => []],
        ] as $fields) {
            $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', ...$fields])->assertForbidden();
        }
        $this->deleteJson("/api/sales/{$sale->id}")->assertForbidden();
        $this->assertSame('2026-09-10', $sale->fresh()->date->toDateString());
        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02'])->assertOk();
        $this->assertSame('300.00', $sale->fresh()->total);
        $this->assertDatabaseHas('good_sale', ['sale_id' => $sale->id, 'quantity' => 3]);
    }

    public function test_user_without_admin_role_or_warehouse_permission_cannot_mutate_sales(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));

        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', 'total' => 1])->assertForbidden();
        $this->deleteJson("/api/sales/{$sale->id}")->assertForbidden();
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 300]);
    }

    #[DataProvider('invalidEdits')]
    public function test_invalid_edit_leaves_sale_lines_and_stock_unchanged(array $changes, string $error): void
    {
        $this->receipt($this->good, 10, 20);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $before = $sale->getAttributes();
        $lineBefore = DB::table('good_sale')->where('sale_id', $sale->id)->sole();
        $movementBefore = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes();
        $payload = [
            'date' => '2026-10-02',
            'goods' => [['id' => $lineBefore->id, ...$this->line($this->good, 4, 120)]],
        ];
        foreach ($changes as $key => $value) {
            data_set($payload, $key, $value);
        }

        $this->patchJson("/api/sales/{$sale->id}", $payload)->assertUnprocessable()->assertJsonValidationErrors($error);

        $this->assertSame($before, $sale->fresh()->getAttributes());
        $this->assertEquals($lineBefore, DB::table('good_sale')->where('sale_id', $sale->id)->sole());
        $this->assertSame($movementBefore, GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes());
        $this->assertEqualsWithDelta(7, $this->balance($this->good), 0.000001);
    }

    public static function invalidEdits(): array
    {
        return [
            'invalid calendar date' => [['date' => '2026-02-30'], 'date'],
            'missing customer' => [['entity_id' => 999999], 'entity_id'],
            'negative total' => [['total' => -1], 'total'],
            'overflowing total' => [['total' => '1e309'], 'total'],
            'zero quantity' => [['goods.0.quantity' => 0], 'goods.0.quantity'],
            'negative price' => [['goods.0.price' => -1], 'goods.0.price'],
            'invalid good' => [['goods.0.good_id' => 999999], 'goods.0.good_id'],
            'inconsistent line total' => [['goods.0.total' => 1], 'goods'],
        ];
    }

    public function test_line_identifiers_must_be_unique_and_belong_to_the_edited_sale(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $other = $this->createSale([$this->line($this->good, 1, 100)]);
        $lineId = $sale->goods->sole()->pivot->id;
        $otherId = $other->goods->sole()->pivot->id;
        $line = ['id' => $lineId, ...$this->line($this->good, 2, 30)];

        foreach ([[$line, $line], [['id' => $otherId, ...$this->line($this->good, 2, 30)]]] as $lines) {
            $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', 'goods' => $lines])->assertUnprocessable();
        }

        $this->assertDatabaseHas('good_sale', ['id' => $lineId, 'quantity' => 3, 'price' => 100]);
        $this->assertDatabaseHas('good_sale', ['id' => $otherId, 'quantity' => 1, 'price' => 100]);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertSame('300.00', $sale->fresh()->total);
    }

    public function test_stock_sync_failure_rolls_back_metadata_line_changes_and_removed_movements(): void
    {
        $this->receipt($this->good, 10, 20);
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $badGood = Good::query()->create(['name' => 'Некорректная стоимость']);
        $this->receipt($badGood, 1, 1e308);
        $this->receipt($badGood, 1, 1e308);
        $before = $sale->getAttributes();
        $lineBefore = DB::table('good_sale')->where('sale_id', $sale->id)->sole();
        $movementBefore = GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes();

        $this->patchJson("/api/sales/{$sale->id}", [
            'date' => '2026-10-02',
            'payment_reference' => 'ROLLBACK',
            'goods' => [
                $this->line($this->good, 5, 200),
                $this->line($badGood, 1, 10),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertSame($before, $sale->fresh()->getAttributes());
        $this->assertEquals($lineBefore, DB::table('good_sale')->where('sale_id', $sale->id)->sole());
        $this->assertSame($movementBefore, GoodStockMovement::query()->where('sale_id', $sale->id)->sole()->getAttributes());
        $this->assertDatabaseCount('good_stock_movements', 4);
        $this->assertEqualsWithDelta(7, $this->balance($this->good), 0.000001);
    }

    public function test_admin_can_delete_sale_and_restore_stock_without_replaying_old_create_request(): void
    {
        $receipt = $this->receipt($this->good, 10, 20);
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
            'date' => '2026-09-10',
            'entity_id' => $this->entity->id,
            'goods' => [$this->line($this->good, 3, 100)],
        ];
        $saleId = $this->postJson('/api/sales', $payload)->assertCreated()->json('data.id');

        $this->deleteJson("/api/sales/{$saleId}")->assertNoContent();

        $this->assertDatabaseMissing('sales', ['id' => $saleId]);
        $this->assertDatabaseMissing('good_sale', ['sale_id' => $saleId]);
        $this->assertDatabaseMissing('good_stock_movements', ['sale_id' => $saleId]);
        $this->assertDatabaseHas('good_stock_movements', ['id' => $receipt->id, 'quantity_delta' => 10]);
        $this->assertEqualsWithDelta(10, $this->balance($this->good), 0.000001);
        $this->getJson("/api/sales/{$saleId}")->assertNotFound();
        $this->postJson('/api/sales', $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('good_stock_movements', 1);
    }

    public function test_shipment_sale_cannot_be_rewritten_or_deleted(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $order = Order::query()->create(['entity_id' => $this->entity->id, 'created_by_user_id' => $this->admin->id]);
        $order->forceFill(['shipped_sale_id' => $sale->id, 'shipped_at' => now()])->save();

        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', 'goods' => []])->assertConflict();
        $this->deleteJson("/api/sales/{$sale->id}")->assertConflict();

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 300]);
        $this->assertDatabaseHas('good_sale', ['sale_id' => $sale->id, 'quantity' => 3]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $sale->id, 'quantity_delta' => -3]);
        $this->assertSame($sale->id, $order->fresh()->shipped_sale_id);
    }

    public function test_bank_allocation_history_blocks_deletion_and_active_allocation_blocks_customer_change(): void
    {
        $sale = $this->createSale([$this->line($this->good, 3, 100)]);
        $allocation = $this->allocate($sale, '100.00');
        $otherEntity = Entity::query()->create(['name' => 'Другой покупатель']);

        $this->patchJson("/api/sales/{$sale->id}", ['date' => '2026-10-02', 'entity_id' => $otherEntity->id])->assertConflict();
        $this->deleteJson("/api/sales/{$sale->id}")->assertConflict();
        app(PaymentAllocationService::class)->reverse($allocation, $this->admin, 'Ошибочная сверка');
        $this->deleteJson("/api/sales/{$sale->id}")->assertConflict();

        $this->assertFalse($allocation->fresh()->is_active);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'entity_id' => $this->entity->id, 'total' => 300]);
        $this->assertDatabaseHas('good_stock_movements', ['sale_id' => $sale->id, 'quantity_delta' => -3]);
    }

    private function createSale(array $lines): Sale
    {
        $id = $this->postJson('/api/sales', [
            'date' => '2026-09-10',
            'entity_id' => $this->entity->id,
            'goods' => $lines,
        ])->assertCreated()->json('data.id');

        return Sale::query()->with('goods')->findOrFail($id);
    }

    private function line(Good $good, float $quantity, float $price): array
    {
        return ['good_id' => $good->id, 'measure_id' => $good->measure_id, 'quantity' => $quantity, 'price' => $price];
    }

    private function receipt(Good $good, float $quantity, float $cost): GoodStockMovement
    {
        return GoodStockMovement::query()->create([
            'warehouse_id' => $this->warehouse->id,
            'good_id' => $good->id,
            'measure_id' => $good->measure_id,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => $quantity,
            'unit_price' => $cost,
            'moved_at' => '2026-09-01',
        ]);
    }

    private function balance(Good $good): float
    {
        return (float) GoodStockMovement::query()->where('warehouse_id', $this->warehouse->id)
            ->where('good_id', $good->id)->where('measure_id', $good->measure_id)->sum('quantity_delta');
    }

    private function allocate(Sale $sale, string $amount): BankTransactionAllocation
    {
        $connection = BankConnection::query()->create(['provider' => 'sber', 'environment' => 'sandbox', 'status' => 'active']);
        $account = BankAccount::query()->create([
            'bank_connection_id' => $connection->id,
            'account_number' => '40702810000000000001',
            'masked_number' => '4070••••••••••••0001',
        ]);
        $transaction = BankTransaction::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account->id,
            'fingerprint' => hash('sha256', (string) Str::uuid()),
            'operation_date' => '2026-09-11',
            'direction' => 'credit',
            'amount' => $amount,
            'currency' => 'RUB',
            'status' => 'posted',
            'imported_at' => now(),
        ]);

        return app(PaymentAllocationService::class)->allocate($transaction, $sale, $amount, AllocationSource::Manual, $this->admin);
    }
}
