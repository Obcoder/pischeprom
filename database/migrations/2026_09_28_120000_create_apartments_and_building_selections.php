<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);
            $table->string('type', 20)->default('apartment');
            $table->timestamps();
            $table->unique(['building_id', 'type', 'number']);
        });

        foreach (['building_order', 'building_unit', 'building_entities'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('apartment_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['building_order', 'building_unit', 'building_entities'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('apartment_id');
            });
        }

        Schema::dropIfExists('apartments');
    }
};
