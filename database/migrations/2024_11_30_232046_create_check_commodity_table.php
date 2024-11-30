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
        Schema::create('check_commodity', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('check_id');
            $table->foreign('check_id')->references('id')->on('checks')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('commodity_id');
            $table->foreign('commodity_id')->references('id')->on('commodities')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['check_id', 'commodity_id']);
            $table->double('quantity')->default(1);
            $table->unsignedBigInteger('measure_id')->nullable();
            $table->foreign('measure_id')->references('id')->on('measures')->onUpdate('cascade')->onDelete('cascade');
            $table->double('price')->default(0);
            $table->double('total_price')->storedAs('quantity * price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_commodity');
    }
};
