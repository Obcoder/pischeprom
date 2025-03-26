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
        Schema::create('good_sale', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('good_id');
            $table->foreign('good_id')->references('id')->on('goods')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('sale_id');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade')->onUpdate('cascade');
            $table->double('quantity')->default(1);
            $table->unsignedBigInteger('measure_id');
            $table->foreign('measure_id')->references('id')->on('measures')->onDelete('cascade')->onUpdate('cascade');
            $table->double('price')->default(0);
            $table->double('total')->storedAs('quantity * price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('good_sale');
    }
};
