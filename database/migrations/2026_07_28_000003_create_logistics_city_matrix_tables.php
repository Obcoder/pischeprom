<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->unique()->constrained('cities')->restrictOnDelete();
            $table->decimal('routing_latitude', 10, 7)->nullable();
            $table->decimal('routing_longitude', 10, 7)->nullable();
            $table->string('coordinate_source', 32)->default('existing');
            $table->string('source_reference', 1024)->nullable();
            $table->boolean('is_matrix_enabled')->default(false)->index();
            $table->dateTime('coordinates_verified_at')->nullable();
            $table->foreignId('coordinates_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['routing_latitude', 'routing_longitude'], 'log_cities_routing_point_idx');
        });

        Schema::create('logistics_city_distances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_city_id')->constrained('cities')->restrictOnDelete();
            $table->foreignId('to_city_id')->constrained('cities')->restrictOnDelete();
            $table->string('routing_profile', 32)->default('truck');
            $table->string('vehicle_profile_hash', 64)->default('default');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('distance_m')->nullable();
            $table->unsignedBigInteger('duration_s')->nullable();
            $table->decimal('from_latitude_snapshot', 10, 7);
            $table->decimal('from_longitude_snapshot', 10, 7);
            $table->decimal('to_latitude_snapshot', 10, 7);
            $table->decimal('to_longitude_snapshot', 10, 7);
            $table->string('provider', 32)->default('valhalla');
            $table->string('routing_engine_version', 128)->nullable();
            $table->string('osm_data_version', 128)->nullable();
            $table->string('request_hash', 64)->index();
            $table->dateTime('calculated_at')->nullable()->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->text('manual_note')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['from_city_id', 'to_city_id', 'routing_profile', 'vehicle_profile_hash'],
                'log_city_distance_pair_profile_unique'
            );
            $table->index(['from_city_id', 'to_city_id'], 'log_city_distance_pair_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE logistics_city_distances ADD CONSTRAINT log_city_distance_different_cities CHECK (from_city_id <> to_city_id)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_city_distances');
        Schema::dropIfExists('logistics_cities');
    }
};
