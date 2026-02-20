<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->id();

            // Код ОКВЭД (например: 62.01)
            $table->string('code', 20)
                ->unique()
                ->comment('ОКВЭД code');

            // Название вида деятельности
            $table->string('title')
                ->comment('Название вида деятельности');

            $table->timestamps();

            // Индекс для быстрого поиска по названию
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industries');
    }
};
