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
        Schema::table('consumptions', function (Blueprint $table) {
            $table->unsignedBigInteger('measure_id')->after('quantity')->nullable();
            $table->foreign('measure_id')->references('id')->on('measures')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumptions', function (Blueprint $table) {
            //
        });
    }
};
