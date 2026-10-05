<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'hs_code' => 6,
        'tn_ved_code' => 10,
        'okpd2_code' => 12,
        'cn_code' => 8,
        'taric_code' => 10,
        'htsus_code' => 10,
        'schedule_b_code' => 10,
        'gtin' => 14,
        'unspsc_code' => 8,
        'cas_number' => 12,
        'eccn_code' => 5,
    ];

    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table): void {
            // CDN URLs may include transformation parameters or signed query strings.
            $table->text('ava_image')->nullable()->change();
            $table->text('ava_thumb')->nullable()->change();
            foreach (self::COLUMNS as $column => $length) {
                $table->string($column, $length)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
            // Keep the compatible TEXT widening: narrowing would truncate long CDN URLs.
        });
    }
};
