<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('commodity_id')
                ->constrained('commodities')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('measure_id')
                ->nullable()
                ->constrained('measures')
                ->nullOnDelete();
            $table->string('type', 40)->index();
            $table->double('quantity_delta');
            $table->double('unit_price')->default(0);
            $table->double('total_price')->storedAs('quantity_delta * unit_price');
            $table->date('moved_at')->index();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->index(['warehouse_id', 'commodity_id'], 'stock_movements_warehouse_commodity_index');
            $table->index(['source_type', 'source_id'], 'stock_movements_source_index');
            $table->unique(['source_type', 'source_id'], 'stock_movements_source_unique');
        });

        $defaultWarehouseId = DB::table('warehouses')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        if ($defaultWarehouseId) {
            DB::statement(
                "INSERT INTO stock_movements (
                    created_at,
                    updated_at,
                    warehouse_id,
                    commodity_id,
                    measure_id,
                    type,
                    quantity_delta,
                    unit_price,
                    moved_at,
                    source_type,
                    source_id,
                    note
                )
                SELECT
                    cc.created_at,
                    cc.updated_at,
                    COALESCE(cc.warehouse_id, ?),
                    cc.commodity_id,
                    cc.measure_id,
                    'check_purchase',
                    cc.quantity,
                    cc.price,
                    ch.date,
                    'check_commodity',
                    cc.id,
                    CONCAT('Check #', cc.check_id)
                FROM check_commodity cc
                INNER JOIN checks ch ON ch.id = cc.check_id",
                [$defaultWarehouseId]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
