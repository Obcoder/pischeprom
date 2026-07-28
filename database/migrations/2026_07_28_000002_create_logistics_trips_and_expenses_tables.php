<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_trips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 64)->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('vehicle_id')->nullable()->constrained('logistics_vehicles')->restrictOnDelete();
            $table->foreignId('carrier_entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('planned_departure_at')->nullable()->index();
            $table->dateTime('planned_arrival_at')->nullable();
            $table->dateTime('actual_departure_at')->nullable()->index();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->text('cargo_description')->nullable();
            $table->decimal('cargo_weight_kg', 12, 3)->nullable();
            $table->decimal('cargo_volume_m3', 10, 3)->nullable();
            $table->unsignedSmallInteger('pallet_count')->nullable();
            $table->string('temperature_mode', 32)->nullable();
            $table->decimal('temperature_min_c', 6, 2)->nullable();
            $table->decimal('temperature_max_c', 6, 2)->nullable();
            $table->unsignedBigInteger('planned_distance_m')->nullable();
            $table->unsignedBigInteger('planned_duration_s')->nullable();
            $table->unsignedBigInteger('actual_distance_m')->nullable();
            $table->string('actual_distance_source', 32)->nullable();
            $table->decimal('odometer_start_km', 12, 1)->nullable();
            $table->decimal('odometer_end_km', 12, 1)->nullable();
            $table->string('routing_profile', 32)->default('truck');
            $table->string('routing_profile_hash', 64)->nullable()->index();
            $table->dateTime('route_calculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'planned_departure_at']);
            $table->index(['carrier_entity_id', 'status']);
            $table->index(['responsible_user_id', 'status']);
        });

        Schema::create('logistics_trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('logistics_trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('city_id')->constrained('cities')->restrictOnDelete();
            $table->string('stop_type', 32)->default('waypoint');
            $table->string('operation_type', 32)->nullable();
            $table->string('address', 1024)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('planned_arrival_at')->nullable();
            $table->dateTime('planned_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->decimal('cargo_weight_change_kg', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'sequence']);
            $table->index(['city_id', 'trip_id']);
            $table->index(['trip_id', 'stop_type']);
        });

        Schema::create('logistics_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('logistics_trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('logistics_trips')->cascadeOnDelete();
            $table->foreignId('check_id')->nullable()->constrained('checks')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('logistics_expense_categories')->restrictOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->char('currency_code', 3)->default('RUB');
            $table->dateTime('occurred_at')->nullable()->index();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_id', 'currency_code']);
            $table->index(['check_id', 'trip_id']);
            $table->index(['expense_category_id', 'occurred_at'], 'log_trip_exp_category_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_trip_expenses');
        Schema::dropIfExists('logistics_expense_categories');
        Schema::dropIfExists('logistics_trip_stops');
        Schema::dropIfExists('logistics_trips');
    }
};
