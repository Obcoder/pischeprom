<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('entity_locations', 'geo_point')) {
                $table->geometry('geo_point', 'point', 4326)
                    ->nullable()
                    ->after('lon');
            }
        });

        Schema::table('gis_route_points', function (Blueprint $table) {
            if (! Schema::hasColumn('gis_route_points', 'geo_point')) {
                $table->geometry('geo_point', 'point', 4326)
                    ->nullable()
                    ->after('lon');
            }
        });

        DB::statement("UPDATE entity_locations SET geo_point = ST_GeomFromText(CONCAT('POINT(', lon, ' ', lat, ')'), 4326) WHERE lat IS NOT NULL AND lon IS NOT NULL AND geo_point IS NULL");
        DB::statement("UPDATE gis_route_points SET geo_point = ST_GeomFromText(CONCAT('POINT(', lon, ' ', lat, ')'), 4326) WHERE lat IS NOT NULL AND lon IS NOT NULL AND geo_point IS NULL");
    }

    public function down(): void
    {
        Schema::table('gis_route_points', function (Blueprint $table) {
            if (Schema::hasColumn('gis_route_points', 'geo_point')) {
                $table->dropColumn('geo_point');
            }
        });

        Schema::table('entity_locations', function (Blueprint $table) {
            if (Schema::hasColumn('entity_locations', 'geo_point')) {
                $table->dropColumn('geo_point');
            }
        });
    }
};
