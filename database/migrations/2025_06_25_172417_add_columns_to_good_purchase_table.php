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
        Schema::table('good_purchase', function (Blueprint $table) {
            $table->double('quantity')->default(1);
            $table->foreignId('measure_id')->nullable()->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->double('price')->default(0);
            $table->foreignId('currency_id')->nullable()->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->double('total')->storedAs('quantity * price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('good_purchase', function (Blueprint $table) {
            //
        });
    }
};
