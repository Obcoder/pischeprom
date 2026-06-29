<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_locations', function (Blueprint $table) {
            $table->foreignId('entity_id')
                ->constrained('entities')
                ->cascadeOnDelete();

            $table->primary('entity_id');
            $table->string('address_text', 1024)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('source', 32)->default('manual')->index();
            $table->string('provider_object_id')->nullable()->index();
            $table->string('precision_level', 32)->default('unknown')->index();
            $table->boolean('is_confirmed')->default(false)->index();
            $table->timestamp('geocoded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['lat', 'lon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_locations');
    }
};
