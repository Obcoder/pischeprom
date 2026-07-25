<?php

use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $goodsWarehouse = DB::table('warehouses')
            ->where('code', Warehouse::GOODS_CODE)
            ->first();

        if ($goodsWarehouse) {
            DB::table('warehouses')
                ->where('id', $goodsWarehouse->id)
                ->update([
                    'name' => 'Склад goods',
                    'is_active' => true,
                    'sort_order' => 0,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('warehouses')->insert([
            'name' => 'Склад goods',
            'code' => Warehouse::GOODS_CODE,
            'address' => null,
            'description' => 'Отдельный склад товаров goods.',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $warehouseId = DB::table('warehouses')
            ->where('code', Warehouse::GOODS_CODE)
            ->value('id');

        if (! $warehouseId) {
            return;
        }

        $isReferenced = DB::table('good_stock_movements')
            ->where('warehouse_id', $warehouseId)
            ->exists()
            || DB::table('stock_movements')
                ->where('warehouse_id', $warehouseId)
                ->exists()
            || DB::table('check_commodity')
                ->where('warehouse_id', $warehouseId)
                ->exists();

        if (! $isReferenced) {
            DB::table('warehouses')->where('id', $warehouseId)->delete();
        }
    }
};
