<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->uuid('row_key')->nullable();
            $table->unique(['supplier_order_id', 'row_key']);
        });

        // Stable identity also lets older snapshots whose product was deleted
        // reopen as independent document rows without dropping their content.
        DB::table('supplier_order_items')->orderBy('id')->chunkById(250, function ($rows) {
            foreach ($rows as $row) {
                DB::table('supplier_order_items')->where('id', $row->id)->update(['row_key' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->dropUnique(['supplier_order_id', 'row_key']);
            $table->dropColumn('row_key');
        });
    }
};
