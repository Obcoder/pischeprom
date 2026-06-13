<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prices');
    }

    public function down(): void
    {
        if (Schema::hasTable('prices')) {
            return;
        }

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->double('price');
            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
