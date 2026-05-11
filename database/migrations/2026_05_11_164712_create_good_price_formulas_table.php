<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_price_formulas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->nullable()
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->foreignId('price_type_id')
                ->nullable()
                ->constrained('price_types')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code')->nullable();

            /*
             * Здесь храним не PHP-код, а JSON-настройки формулы:
             * markup_type, markup_value, target_margin_percent,
             * delivery_per_kg, loss_percent, rounding_step и т.д.
             */
            $table->json('formula')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['good_id', 'is_default']);
            $table->index(['price_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_price_formulas');
    }
};
