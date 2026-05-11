<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_price_calculations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->foreignId('purchase_id')
                ->nullable()
                ->constrained('purchases')
                ->nullOnDelete();

            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->nullOnDelete();

            $table->foreignId('price_type_id')
                ->nullable()
                ->constrained('price_types')
                ->nullOnDelete();

            $table->foreignId('formula_id')
                ->nullable()
                ->constrained('good_price_formulas')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name')->nullable();
            $table->text('comment')->nullable();

            $table->json('input')->nullable();
            $table->json('result')->nullable();

            $table->decimal('purchase_net_per_kg', 16, 4)->nullable();
            $table->decimal('sale_net_per_kg', 16, 4)->nullable();
            $table->decimal('sale_gross_per_kg', 16, 4)->nullable();

            $table->decimal('sale_net_per_box', 16, 4)->nullable();
            $table->decimal('sale_gross_per_box', 16, 4)->nullable();

            $table->decimal('profit_per_kg', 16, 4)->nullable();
            $table->decimal('margin_percent', 10, 4)->nullable();
            $table->decimal('markup_percent', 10, 4)->nullable();

            $table->timestamps();

            $table->index(['good_id', 'created_at']);
            $table->index(['good_id', 'price_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_price_calculations');
    }
};
