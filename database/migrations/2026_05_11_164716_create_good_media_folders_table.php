<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_media_folders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('good_media_folders')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('path');

            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_archive')->default(false);

            $table->timestamps();

            $table->index(['good_id', 'parent_id']);
            $table->index(['good_id', 'is_archive']);
            $table->unique(['good_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_media_folders');
    }
};
