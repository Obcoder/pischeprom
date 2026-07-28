<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_trip_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('logistics_trips')->cascadeOnDelete();
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('routing_profile', 32)->default('truck');
            $table->string('vehicle_profile_hash', 64)->default('default');
            $table->string('request_hash', 64)->index();
            $table->unsignedBigInteger('distance_m')->nullable();
            $table->unsignedBigInteger('duration_s')->nullable();
            $table->longText('shape_polyline6')->nullable();
            $table->json('legs')->nullable();
            $table->json('routing_options')->nullable();
            $table->string('provider', 32)->default('valhalla');
            $table->string('routing_engine_version', 128)->nullable();
            $table->string('osm_data_version', 128)->nullable();
            $table->dateTime('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_id', 'is_current']);
            $table->index(['trip_id', 'calculated_at']);
        });

        Schema::create('logistics_routing_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('operation_type', 32)->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('routing_profile', 32)->default('truck');
            $table->unsignedInteger('total_pairs')->default(0);
            $table->unsignedInteger('completed_pairs')->default(0);
            $table->unsignedInteger('failed_pairs')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('parameters')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_routing_runs');
        Schema::dropIfExists('logistics_trip_routes');
    }
};
