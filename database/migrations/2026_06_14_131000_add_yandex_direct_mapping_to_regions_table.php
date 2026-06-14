<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->json('yandex_direct_region_ids')->nullable()->after('area');
            $table->boolean('use_for_yandex_direct')->default(false)->after('yandex_direct_region_ids');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['yandex_direct_region_ids', 'use_for_yandex_direct']);
        });
    }
};
