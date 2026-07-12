<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('good_id')
                ->nullable()
                ->constrained('goods')
                ->nullOnDelete();
            $table->string('good_name');
            $table->string('good_slug')->nullable();
            $table->text('image_url')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('denominator', 12, 3)->nullable();
            $table->decimal('line_weight', 16, 4)->nullable();
            $table->decimal('price_gross', 16, 4)->nullable();
            $table->string('currency_code', 8)->default('RUB');
            $table->decimal('line_total', 16, 4)->nullable();
            $table->string('country_name')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
