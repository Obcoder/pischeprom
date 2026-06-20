<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('producer_unit_id')->constrained('units')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('consumer_unit_id')->constrained('units')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('status', 32)->default('draft')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['field_id', 'producer_unit_id', 'consumer_unit_id'], 'field_matches_unique_pair');
            $table->index(['field_id', 'consumer_unit_id'], 'field_matches_field_consumer_idx');
            $table->index(['field_id', 'producer_unit_id'], 'field_matches_field_producer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_matches');
    }
};
