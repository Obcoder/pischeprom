<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodity_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('commodity_id')
                ->constrained('commodities')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('disk')->default('yandex');
            $table->string('path')->unique();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_ava')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['commodity_id', 'filename']);
            $table->index(['commodity_id', 'is_ava']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_media');
    }
};
