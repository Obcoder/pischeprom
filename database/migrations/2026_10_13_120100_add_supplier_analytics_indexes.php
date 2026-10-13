<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', fn (Blueprint $table) => $table->index(['unit_id', 'sent_at'], 'supplier_orders_unit_sent_idx'));
        Schema::table('purchases', fn (Blueprint $table) => $table->index(['entity_id', 'date'], 'purchases_entity_date_idx'));
        Schema::table('entity_unit', function (Blueprint $table): void {
            $table->index(['unit_id', 'entity_id'], 'entity_unit_unit_entity_idx');
            $table->index(['entity_id', 'unit_id'], 'entity_unit_entity_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entity_unit', function (Blueprint $table): void {
            $table->dropIndex('entity_unit_unit_entity_idx');
            $table->dropIndex('entity_unit_entity_unit_idx');
        });
        Schema::table('purchases', fn (Blueprint $table) => $table->dropIndex('purchases_entity_date_idx'));
        Schema::table('supplier_orders', fn (Blueprint $table) => $table->dropIndex('supplier_orders_unit_sent_idx'));
    }
};
