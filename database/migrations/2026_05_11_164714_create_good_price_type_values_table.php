<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_price_type_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->foreignId('price_type_id')
                ->constrained('price_types')
                ->cascadeOnDelete();

            $table->foreignId('calculation_id')
                ->nullable()
                ->constrained('good_price_calculations')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('price_net', 16, 4)->nullable();
            $table->decimal('price_gross', 16, 4)->nullable();
            $table->decimal('vat_rate', 10, 4)->nullable();

            $table->boolean('is_manual')->default(false);
            $table->text('manual_comment')->nullable();

            $table->boolean('is_published')->default(false);

            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->timestamps();

            $table->index(['good_id', 'price_type_id']);
            $table->index(['is_published', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_price_type_values');
    }
};
