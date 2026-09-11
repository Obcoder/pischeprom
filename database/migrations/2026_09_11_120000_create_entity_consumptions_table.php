<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 3)->nullable();
            $table->foreignId('measure_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('potential');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_consumptions');
    }
};
