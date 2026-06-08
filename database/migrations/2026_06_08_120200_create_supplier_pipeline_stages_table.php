<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_pipeline_id')
                ->constrained('supplier_pipelines')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['supplier_pipeline_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_pipeline_stages');
    }
};
