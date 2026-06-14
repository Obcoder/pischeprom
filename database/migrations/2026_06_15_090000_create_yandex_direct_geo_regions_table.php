<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yandex_direct_geo_regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_region_id')->unique();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('type')->nullable()->index();
            $table->json('parent_names')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yandex_direct_geo_regions');
    }
};
