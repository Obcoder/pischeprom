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
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            //
        });
    }
};
