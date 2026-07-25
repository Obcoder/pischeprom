<?php

namespace Tests\Feature;

use App\Models\GoodStockMovement;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PurchaseGoodsStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('cache.default', 'array');
        config()->set('queue.default', 'sync');
        DB::purge();
        DB::setDefaultConnection('sqlite');
        Queue::fake();

        $this->createTestSchema();
    }

    public function test_purchase_lifecycle_keeps_good_purchase_and_goods_stock_in_sync(): void
    {
        $warehouseId = $this->createGoodsWarehouse();
        $entityId = $this->createEntity('Поставщик');
        $measureId = $this->createMeasure('шт.');
        $currencyId = $this->createCurrency('RUB');
        $firstGoodId = $this->createGood('Первый товар');
        $secondGoodId = $this->createGood('Второй товар');
        $service = app(PurchaseService::class);

        $purchase = $service->store([
            'date' => '2026-07-20',
            'entity_id' => $entityId,
            'items' => [[
                'good_id' => $firstGoodId,
                'quantity' => 2,
                'measure_id' => $measureId,
                'price' => 125,
                'currency_id' => $currencyId,
            ]],
        ]);

        $firstPivotId = (int) DB::table('good_purchase')
            ->where('purchase_id', $purchase->id)
            ->value('id');
        $firstMovementId = (int) DB::table('good_stock_movements')
            ->where('purchase_id', $purchase->id)
            ->value('id');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('good_purchase', [
            'id' => $firstPivotId,
            'purchase_id' => $purchase->id,
            'good_id' => $firstGoodId,
            'quantity' => 2,
            'measure_id' => $measureId,
            'price' => 125,
            'currency_id' => $currencyId,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $firstMovementId,
            'warehouse_id' => $warehouseId,
            'good_id' => $firstGoodId,
            'measure_id' => $measureId,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => 2,
            'unit_price' => 125,
            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
            'source_id' => $firstPivotId,
            'purchase_id' => $purchase->id,
        ]);
        $this->assertSame(
            '2026-07-20',
            GoodStockMovement::query()
                ->findOrFail($firstMovementId)
                ->moved_at
                ->toDateString()
        );

        $updatedData = [
            'date' => '2026-07-21',
            'entity_id' => $entityId,
            'items' => [
                [
                    'good_id' => $firstGoodId,
                    'quantity' => 4,
                    'measure_id' => $measureId,
                    'price' => 130,
                    'currency_id' => $currencyId,
                ],
                [
                    'good_id' => $secondGoodId,
                    'quantity' => 3,
                    'measure_id' => $measureId,
                    'price' => 80,
                    'currency_id' => $currencyId,
                ],
            ],
        ];

        $purchase = $service->update($purchase, $updatedData);
        $service->update($purchase, $updatedData);

        $this->assertDatabaseCount('good_purchase', 2);
        $this->assertDatabaseCount('good_stock_movements', 2);
        $this->assertDatabaseHas('good_purchase', [
            'id' => $firstPivotId,
            'quantity' => 4,
            'price' => 130,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $firstMovementId,
            'source_id' => $firstPivotId,
            'quantity_delta' => 4,
            'unit_price' => 130,
        ]);
        $this->assertSame(
            '2026-07-21',
            GoodStockMovement::query()
                ->findOrFail($firstMovementId)
                ->moved_at
                ->toDateString()
        );
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'amount' => 760,
        ]);

        $secondPivotId = (int) DB::table('good_purchase')
            ->where('purchase_id', $purchase->id)
            ->where('good_id', $secondGoodId)
            ->value('id');

        $purchase = $service->update($purchase, [
            'date' => '2026-07-22',
            'entity_id' => $entityId,
            'items' => [[
                'good_id' => $secondGoodId,
                'quantity' => 5,
                'measure_id' => $measureId,
                'price' => 90,
                'currency_id' => $currencyId,
            ]],
        ]);

        $this->assertDatabaseCount('good_purchase', 1);
        $this->assertDatabaseCount('good_stock_movements', 1);
        $this->assertDatabaseMissing('good_purchase', ['id' => $firstPivotId]);
        $this->assertDatabaseMissing('good_stock_movements', [
            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
            'source_id' => $firstPivotId,
        ]);
        $this->assertDatabaseHas('good_purchase', [
            'id' => $secondPivotId,
            'purchase_id' => $purchase->id,
            'good_id' => $secondGoodId,
            'quantity' => 5,
            'price' => 90,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
            'source_id' => $secondPivotId,
            'purchase_id' => $purchase->id,
            'good_id' => $secondGoodId,
            'quantity_delta' => 5,
            'unit_price' => 90,
        ]);
        $this->assertSame(
            '2026-07-22',
            GoodStockMovement::query()
                ->where('source_id', $secondPivotId)
                ->firstOrFail()
                ->moved_at
                ->toDateString()
        );

        $service->delete($purchase);

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('good_purchase', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_purchase_is_rolled_back_when_the_goods_warehouse_is_missing(): void
    {
        $entityId = $this->createEntity('Поставщик');
        $goodId = $this->createGood('Товар');

        try {
            app(PurchaseService::class)->store([
                'date' => '2026-07-20',
                'entity_id' => $entityId,
                'items' => [[
                    'good_id' => $goodId,
                    'quantity' => 2,
                    'measure_id' => null,
                    'price' => 100,
                    'currency_id' => null,
                ]],
            ]);

            $this->fail('Purchase creation should fail without the goods warehouse.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Системный склад goods не найден.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('good_purchase', 0);
        $this->assertDatabaseCount('good_stock_movements', 0);
    }

    public function test_purchase_stock_movement_cannot_be_changed_outside_purchase(): void
    {
        $warehouseId = $this->createGoodsWarehouse();
        $entityId = $this->createEntity('Поставщик');
        $goodId = $this->createGood('Товар');
        $purchase = app(PurchaseService::class)->store([
            'date' => '2026-07-20',
            'entity_id' => $entityId,
            'items' => [[
                'good_id' => $goodId,
                'quantity' => 2,
                'measure_id' => null,
                'price' => 100,
                'currency_id' => null,
            ]],
        ]);
        $movement = GoodStockMovement::query()->firstOrFail();

        $this->patchJson(route('good-stock-movements.update', $movement), [
            'warehouse_id' => $warehouseId,
            'good_id' => $goodId,
            'measure_id' => null,
            'type' => GoodStockMovement::TYPE_WRITE_OFF,
            'quantity' => 1,
            'unit_price' => 100,
            'moved_at' => '2026-07-21',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Это движение создано закупкой и редактируется через Purchase.'
            );

        $this->deleteJson(route('good-stock-movements.destroy', $movement))
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Это движение создано закупкой и редактируется через Purchase.'
            );

        $this->assertDatabaseHas('good_stock_movements', [
            'id' => $movement->id,
            'purchase_id' => $purchase->id,
            'quantity_delta' => 2,
        ]);
    }

    public function test_migration_backfills_existing_good_purchase_rows(): void
    {
        $warehouseId = $this->createGoodsWarehouse();
        $entityId = $this->createEntity('Поставщик');
        $goodId = $this->createGood('Исторический товар');
        $measureId = $this->createMeasure('кг');
        $purchaseId = DB::table('purchases')->insertGetId([
            'date' => '2026-06-10',
            'entity_id' => $entityId,
            'amount' => 525,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pivotId = DB::table('good_purchase')->insertGetId([
            'good_id' => $goodId,
            'purchase_id' => $purchaseId,
            'quantity' => 3.5,
            'measure_id' => $measureId,
            'price' => 150,
            'currency_id' => null,
            'total' => 525,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::drop('good_stock_movements');
        $this->createLegacyGoodStockMovementsTable();

        $migration = require database_path(
            'migrations/2026_07_25_000002_link_good_purchases_to_goods_stock.php'
        );
        $migration->up();

        $this->assertDatabaseHas('good_purchase', [
            'id' => $pivotId,
            'purchase_id' => $purchaseId,
            'good_id' => $goodId,
            'quantity' => 3.5,
        ]);
        $this->assertDatabaseHas('good_stock_movements', [
            'warehouse_id' => $warehouseId,
            'good_id' => $goodId,
            'measure_id' => $measureId,
            'type' => GoodStockMovement::TYPE_RECEIPT,
            'quantity_delta' => 3.5,
            'unit_price' => 150,
            'moved_at' => '2026-06-10',
            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
            'source_id' => $pivotId,
            'purchase_id' => $purchaseId,
        ]);
        $this->assertDatabaseHas('good_stock_availabilities', [
            'good_id' => $goodId,
            'is_in_stock' => true,
        ]);
        $this->assertDatabaseHas('good_seos', [
            'good_id' => $goodId,
            'availability_status' => 'in_stock',
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(500);
            $table->timestamps();
        });

        Schema::create('goods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('ava_image')->nullable();
            $table->string('ava_thumb')->nullable();
            $table->timestamps();
        });

        Schema::create('entity_classifications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('entity_classification_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->timestamps();
        });

        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('building_type_id')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('building_entities', function (Blueprint $table): void {
            $table->unsignedBigInteger('building_id');
            $table->unsignedBigInteger('entity_id');
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->foreignId('entity_id')
                ->constrained('entities')
                ->cascadeOnDelete();
            $table->double('amount')->default(0);
            $table->timestamps();
        });

        Schema::create('measures', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('good_purchase', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();
            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->cascadeOnDelete();
            $table->double('quantity')->default(1);
            $table->foreignId('measure_id')->nullable();
            $table->double('price')->default(0);
            $table->foreignId('currency_id')->nullable();
            $table->double('total')->default(0);
            $table->timestamps();
        });

        $this->createGoodStockMovementsTable();

        Schema::create('good_stock_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('good_id')->unique();
            $table->boolean('is_in_stock')->default(false);
            $table->timestamp('became_available_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('good_seos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('good_id')->unique();
            $table->string('availability_status')->default('on_request');
            $table->timestamps();
        });
    }

    private function createGoodStockMovementsTable(): void
    {
        Schema::create('good_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('good_id')->constrained('goods');
            $table->foreignId('measure_id')->nullable();
            $table->string('type');
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->date('moved_at');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('purchase_id')
                ->nullable()
                ->constrained('purchases')
                ->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
        });
    }

    private function createLegacyGoodStockMovementsTable(): void
    {
        Schema::create('good_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('good_id')->constrained('goods');
            $table->foreignId('measure_id')->nullable();
            $table->string('type');
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->date('moved_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    private function createGoodsWarehouse(): int
    {
        return DB::table('warehouses')->insertGetId([
            'name' => 'Склад goods',
            'code' => Warehouse::GOODS_CODE,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEntity(string $name): int
    {
        return DB::table('entities')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createGood(string $name): int
    {
        return DB::table('goods')->insertGetId([
            'name' => $name,
            'slug' => str($name)->slug(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMeasure(string $name): int
    {
        return DB::table('measures')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCurrency(string $name): int
    {
        return DB::table('currencies')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
