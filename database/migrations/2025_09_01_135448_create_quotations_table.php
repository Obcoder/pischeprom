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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('good_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->double('price');
            $table->foreignId('measure_id')->nullable()->constrained()->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
