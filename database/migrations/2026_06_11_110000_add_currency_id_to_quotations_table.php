<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('quotations', 'currency_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->foreignId('currency_id')
                    ->nullable()
                    ->after('price')
                    ->constrained('currencies')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotations', 'currency_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            });
        }
    }
};
