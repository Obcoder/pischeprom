<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('check_commodity'))->pluck('name');

        if (! $indexes->contains('check_commodity_check_id_commodity_id_index')) {
            Schema::table('check_commodity', function (Blueprint $table) {
                $table->index(['check_id', 'commodity_id'], 'check_commodity_check_id_commodity_id_index');
            });
        }

        $indexes = collect(Schema::getIndexes('check_commodity'))->pluck('name');

        if ($indexes->contains('check_commodity_check_id_commodity_id_unique')) {
            Schema::table('check_commodity', function (Blueprint $table) {
                $table->dropUnique('check_commodity_check_id_commodity_id_unique');
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('check_commodity'))->pluck('name');

        if (! $indexes->contains('check_commodity_check_id_commodity_id_unique')) {
            Schema::table('check_commodity', function (Blueprint $table) {
                $table->unique(['check_id', 'commodity_id'], 'check_commodity_check_id_commodity_id_unique');
            });
        }

        $indexes = collect(Schema::getIndexes('check_commodity'))->pluck('name');

        if ($indexes->contains('check_commodity_check_id_commodity_id_index')) {
            Schema::table('check_commodity', function (Blueprint $table) {
                $table->dropIndex('check_commodity_check_id_commodity_id_index');
            });
        }
    }
};
