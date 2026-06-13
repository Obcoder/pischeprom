<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasIndex('entities', 'entities_name_unique')) {
                $table->dropUnique('entities_name_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasIndex('entities', 'entities_name_unique')) {
                $table->unique('name', 'entities_name_unique');
            }
        });
    }
};
