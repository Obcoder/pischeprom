<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_pipeline_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_pipeline_id')
                ->constrained('supplier_pipelines')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('supplier_pipeline_stage_id')
                ->nullable()
                ->constrained('supplier_pipeline_stages')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('next_contact_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['supplier_pipeline_id', 'unit_id']);
            $table->index(['supplier_pipeline_stage_id', 'sort_order'], 'sp_cards_stage_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_pipeline_cards');
    }
};
