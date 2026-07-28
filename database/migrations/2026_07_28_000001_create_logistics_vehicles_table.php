<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number', 32)->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('vin', 32)->nullable()->unique();
            $table->string('vehicle_type', 32)->default('truck')->index();
            $table->foreignId('owner_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->decimal('payload_capacity_kg', 12, 3)->nullable();
            $table->decimal('cargo_volume_m3', 10, 3)->nullable();
            $table->decimal('curb_weight_kg', 12, 3)->nullable();
            $table->decimal('gross_weight_kg', 12, 3)->nullable();
            $table->decimal('length_m', 8, 3)->nullable();
            $table->decimal('width_m', 8, 3)->nullable();
            $table->decimal('height_m', 8, 3)->nullable();
            $table->unsignedTinyInteger('axle_count')->nullable();
            $table->decimal('max_axle_load_t', 8, 3)->nullable();
            $table->string('fuel_type', 32)->nullable();
            $table->decimal('fuel_tank_capacity_l', 10, 3)->nullable();
            $table->decimal('average_fuel_consumption_l_per_100km', 8, 3)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'status']);
            $table->index(['make', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_vehicles');
    }
};
