<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_route_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('provider', 32)->index();
            $table->string('transport_mode', 32)->default('car')->index();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->json('route_geometry_json')->nullable();
            $table->json('provider_response_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_route_drafts');
    }
};
