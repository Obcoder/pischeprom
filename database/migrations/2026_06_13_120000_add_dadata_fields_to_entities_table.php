<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'dadata_raw')) {
                $table->json('dadata_raw')->nullable()->after('country_id');
            }

            if (! Schema::hasColumn('entities', 'dadata_loaded_at')) {
                $table->timestamp('dadata_loaded_at')->nullable()->after('dadata_raw');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'dadata_loaded_at')) {
                $table->dropColumn('dadata_loaded_at');
            }

            if (Schema::hasColumn('entities', 'dadata_raw')) {
                $table->dropColumn('dadata_raw');
            }
        });
    }
};
