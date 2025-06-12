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
        Schema::table('uris', function (Blueprint $table) {
            $table->boolean('is_valid')->default(true);
            $table->boolean('follow')->default(false);
            $table->boolean('has_brilliant_foremost_design')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uris', function (Blueprint $table) {
            //
        });
    }
};
