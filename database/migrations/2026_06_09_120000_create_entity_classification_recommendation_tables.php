<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_classification_good', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_classification_id')->constrained('entity_classifications')->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['entity_classification_id', 'good_id'], 'ec_good_unique');
            $table->index(['good_id', 'entity_classification_id'], 'ec_good_good_index');
        });

        Schema::create('entity_classification_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_classification_id')->constrained('entity_classifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['entity_classification_id', 'user_id'], 'ec_user_unique');
            $table->index(['user_id', 'entity_classification_id'], 'ec_user_user_index');
        });

        Schema::create('entity_classification_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_classification_id')->constrained('entity_classifications')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['entity_classification_id', 'unit_id'], 'ec_unit_unique');
            $table->index(['unit_id', 'entity_classification_id'], 'ec_unit_unit_index');
        });

        Schema::create('entity_entity_classification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_classification_id')->constrained('entity_classifications')->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['entity_classification_id', 'entity_id'], 'ec_entity_unique');
            $table->index(['entity_id', 'entity_classification_id'], 'ec_entity_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_entity_classification');
        Schema::dropIfExists('entity_classification_unit');
        Schema::dropIfExists('entity_classification_user');
        Schema::dropIfExists('entity_classification_good');
    }
};
