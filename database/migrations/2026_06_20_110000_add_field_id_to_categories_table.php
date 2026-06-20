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
        if (Schema::hasColumn('categories', 'field_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('field_id')
                ->nullable()
                ->constrained('fields')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('categories', 'field_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_id');
        });
    }
};
