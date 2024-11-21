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
        Schema::table('stage_unit', function (Blueprint $table) {
            $table->date('startDate')->default(DB::raw('CURRENT_TIMESTAMP'))->after('id');
            $table->date('endDate')->nullable()->after('startDate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stage_unit', function (Blueprint $table) {
            //
        });
    }
};
