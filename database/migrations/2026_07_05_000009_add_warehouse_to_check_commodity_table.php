<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_commodity', function (Blueprint $table) {
            if (! Schema::hasColumn('check_commodity', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('commodity_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }
        });

        $defaultWarehouseId = DB::table('warehouses')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        if ($defaultWarehouseId) {
            DB::table('check_commodity')
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWarehouseId]);
        }
    }

    public function down(): void
    {
        Schema::table('check_commodity', function (Blueprint $table) {
            if (Schema::hasColumn('check_commodity', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
        });
    }
};
