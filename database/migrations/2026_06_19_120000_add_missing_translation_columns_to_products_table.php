<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'ur',
        'nl',
        'fa',
        'ja',
        'ko',
        'he',
        'idn',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (!Schema::hasColumn('products', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (array_reverse(self::COLUMNS) as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
