<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies')
                ->nullOnDelete();

            $table->decimal('markup_percent', 10, 4)->nullable();
            $table->decimal('target_margin_percent', 10, 4)->nullable();
            $table->decimal('rounding_step', 12, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(100);

            $table->timestamps();

            $table->index(['is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_types');
    }
};
