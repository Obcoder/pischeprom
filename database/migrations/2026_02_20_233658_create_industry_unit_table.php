<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industry_unit', function (Blueprint $table) {
            $table->id();

            $table->foreignId('industry_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->cascadeOnDelete();

            // Главный ОКВЭД
            $table->boolean('is_primary')
                ->default(false)
                ->comment('Основной ОКВЭД юнита');

            $table->timestamps();

            // защита от дублей
            $table->unique(['industry_id', 'unit_id']);

            // индекс для быстрого поиска основного
            $table->index(['unit_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_unit');
    }
};
