<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_draft_id')->constrained('gis_route_drafts')->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('address_text', 1024)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->string('point_type', 32)->default('manual')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['route_draft_id', 'sequence_no']);
            $table->index(['entity_id', 'sequence_no']);
            $table->index(['lat', 'lon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_route_points');
    }
};
