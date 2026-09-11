<?php

namespace Tests\Feature;

use App\Http\Controllers\API\GoodStockMovementController;
use App\Http\Middleware\EnsureWarehouseMutationAllowed;
use App\Models\GoodStockMovement;
use App\Models\Warehouse;
use App\Services\Goods\GoodStockMutationService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GoodStockMutationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        DB::purge();
        DB::setDefaultConnection('sqlite');
        Queue::fake();
        // Authorization has separate coverage in SaleGoodsStockTest.
        $this->withoutMiddleware([Authenticate::class, EnsureEmailIsVerified::class, EnsureWarehouseMutationAllowed::class]);

        Schema::create('goods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('ava_image')->nullable();
            $table->string('ava_thumb')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('measures', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('good_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('good_id')->constrained('goods');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('measure_id')->nullable()->constrained('measures');
            $table->string('type');
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->date('moved_at');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });

        DB::table('goods')->insert([
            ['id' => 1, 'name' => 'Первый товар'],
            ['id' => 2, 'name' => 'Второй товар'],
        ]);
        DB::table('warehouses')->insert([
            ['id' => 1, 'name' => 'Склад goods', 'code' => Warehouse::GOODS_CODE],
            ['id' => 2, 'name' => 'Другой склад', 'code' => 'other'],
        ]);
        DB::table('measures')->insert([
            ['id' => 1, 'name' => 'шт.'],
            ['id' => 2, 'name' => 'кг'],
        ]);
    }

    public function test_manual_write_off_cannot_use_other_warehouses_or_measures(): void
    {
        $this->movement(10, ['warehouse_id' => 2]);
        $this->movement(10, ['measure_id' => 2]);
        $this->movement(10, ['measure_id' => null]);

        $this->postJson(route('good-stock-movements.store'), $this->payload(-1))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertDatabaseCount('good_stock_movements', 3);
    }

    public function test_used_receipt_cannot_be_reduced_deleted_or_moved_to_another_good(): void
    {
        $receipt = $this->movement(10);
        $this->movement(-8);

        $this->patchJson(route('good-stock-movements.update', $receipt), $this->payload(7))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');
        $this->patchJson(route('good-stock-movements.update', $receipt), $this->payload(10, ['good_id' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');
        $this->deleteJson(route('good-stock-movements.destroy', $receipt))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $receipt->id, 'good_id' => 1, 'quantity_delta' => 10,
        ]);
        $this->assertDatabaseCount('good_stock_movements', 2);
    }

    public function test_existing_shortage_can_be_improved_but_cannot_be_increased(): void
    {
        app(GoodStockMutationService::class)->run([1], fn () => $this->movement(-3), true);

        $this->postJson(route('good-stock-movements.store'), $this->payload(1))->assertCreated();
        $this->postJson(route('good-stock-movements.store'), $this->payload(-1))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertEquals(-2, DB::table('good_stock_movements')->sum('quantity_delta'));
        $this->assertDatabaseCount('good_stock_movements', 2);
    }

    public function test_failed_multi_good_mutation_rolls_back_every_movement(): void
    {
        $this->movement(5);

        try {
            app(GoodStockMutationService::class)->run([2, 1], function (): void {
                $this->movement(-2);
                $this->movement(-1, ['good_id' => 2]);
            });
            $this->fail('A shortage must reject the complete operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('goods', $exception->errors());
        }

        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertEquals(5, DB::table('good_stock_movements')->sum('quantity_delta'));
    }

    public function test_minimum_quantity_cannot_create_or_increase_a_shortage(): void
    {
        $this->postJson(route('good-stock-movements.store'), $this->payload(-0.000001))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');
        $this->movement(-1);
        $this->postJson(route('good-stock-movements.store'), $this->payload(-0.000001))
            ->assertUnprocessable()->assertJsonValidationErrors('goods');

        $this->assertEquals(-1, DB::table('good_stock_movements')->sum('quantity_delta'));
    }

    public function test_non_finite_quantity_price_and_value_are_rejected_before_insertion(): void
    {
        foreach ([
            ['quantity' => '1e309'],
            ['unit_price' => '1e309'],
            ['quantity' => '1e200', 'unit_price' => '1e200'],
        ] as $invalid) {
            $this->postJson(route('good-stock-movements.store'), array_replace($this->payload(1), $invalid))
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_sale_movement_cannot_be_changed_through_manual_api(): void
    {
        $movement = $this->movement(-1, [
            'source_type' => GoodStockMovement::SOURCE_GOOD_SALE,
            'source_id' => 7,
            'sale_id' => 4,
        ]);

        $this->patchJson(route('good-stock-movements.update', $movement), $this->payload(10))
            ->assertUnprocessable()->assertJsonPath('message', 'Это движение создано продажей и редактируется через Sale.');
        $this->deleteJson(route('good-stock-movements.destroy', $movement))->assertUnprocessable();

        $this->assertDatabaseHas('good_stock_movements', ['id' => $movement->id, 'quantity_delta' => -1]);
    }

    public function test_stale_manual_movement_cannot_change_a_good_without_locking_it(): void
    {
        $stale = $this->movement(10);
        DB::table('good_stock_movements')->where('id', $stale->id)->update(['good_id' => 2]);

        try {
            app(GoodStockMovementController::class)->update(
                Request::create('/stock', 'PATCH', $this->payload(8)),
                $stale
            );
            $this->fail('A stale movement must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('уже изменён', $exception->errors()['goods'][0]);
        }

        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $stale->id, 'good_id' => 2, 'quantity_delta' => 10,
        ]);
    }

    public function test_system_warehouse_and_stock_history_are_protected(): void
    {
        $movement = $this->movement(2, ['warehouse_id' => 2]);

        $this->patchJson(route('warehouses.update', 1), ['name' => 'Склад goods', 'code' => 'renamed'])
            ->assertUnprocessable();
        $this->deleteJson(route('warehouses.destroy', 1))->assertUnprocessable();
        $this->deleteJson(route('warehouses.destroy', 2))->assertUnprocessable();

        $this->assertDatabaseCount('warehouses', 2);
        $this->assertDatabaseHas('good_stock_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('warehouses', ['id' => 1, 'code' => Warehouse::GOODS_CODE]);
    }

    private function movement(float $quantity, array $overrides = []): GoodStockMovement
    {
        return GoodStockMovement::query()->create(array_replace([
            'warehouse_id' => 1,
            'good_id' => 1,
            'measure_id' => 1,
            'type' => $quantity < 0 ? GoodStockMovement::TYPE_WRITE_OFF : GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => $quantity,
            'unit_price' => 100,
            'moved_at' => '2026-09-11',
        ], $overrides));
    }

    private function payload(float $quantity, array $overrides = []): array
    {
        return array_replace([
            'warehouse_id' => 1,
            'good_id' => 1,
            'measure_id' => 1,
            'type' => $quantity < 0 ? GoodStockMovement::TYPE_WRITE_OFF : GoodStockMovement::TYPE_RECEIPT,
            'quantity' => abs($quantity),
            'unit_price' => 100,
            'moved_at' => '2026-09-11',
        ], $overrides);
    }
}
