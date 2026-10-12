<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_pricing_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->decimal('value', 16, 4);
            $table->string('percentage_base', 30)->default('purchase_net');
            $table->boolean('includes_vat')->default(false);
            $table->decimal('vat_rate', 7, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->json('measurement');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['good_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_pricing_elements');
    }
};
