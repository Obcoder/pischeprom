<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_stock_movements', function (Blueprint $table): void {
            $table->foreignId('sale_id')->nullable()->constrained('sales')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->index(['source_type', 'sale_id']);
        });

        // Deleting a dictionary or a supplier must not cascade into posted stock.
        foreach (['warehouse_id' => 'warehouses', 'good_id' => 'goods', 'measure_id' => 'measures', 'purchase_id' => 'purchases'] as $column => $parent) {
            Schema::table('good_stock_movements', function (Blueprint $table) use ($column, $parent): void {
                $table->dropForeign([$column]);
                $table->foreign($column)->references('id')->on($parent)->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Never silently restore stock by dropping the provenance of posted sales.
        if (DB::table('good_stock_movements')->whereNotNull('sale_id')->exists()) {
            throw new RuntimeException('Откат запрещён: существуют складские движения Sale.');
        }

        Schema::table('good_stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropIndex(['source_type', 'sale_id']);
            $table->dropColumn('sale_id');
        });

        foreach (['warehouse_id' => 'warehouses', 'good_id' => 'goods', 'measure_id' => 'measures', 'purchase_id' => 'purchases'] as $column => $parent) {
            Schema::table('good_stock_movements', function (Blueprint $table) use ($column, $parent): void {
                $table->dropForeign([$column]);
                $foreign = $table->foreign($column)->references('id')->on($parent);
                if ($column === 'measure_id') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->cascadeOnUpdate()->cascadeOnDelete();
                }
            });
        }
    }
};
