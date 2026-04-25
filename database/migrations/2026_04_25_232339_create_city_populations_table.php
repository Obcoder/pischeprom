<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_populations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('city_id')
                ->constrained('cities')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('population');

            $table->timestamps();

            $table->unique(['city_id', 'year']);
            $table->index(['city_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_populations');
    }
};
