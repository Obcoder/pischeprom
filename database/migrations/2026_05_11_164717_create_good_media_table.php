<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('good_id')
                ->constrained('goods')
                ->cascadeOnDelete();

            $table->foreignId('folder_id')
                ->nullable()
                ->constrained('good_media_folders')
                ->nullOnDelete();

            $table->string('type'); // image | video | document

            $table->string('disk')->default('yandex');

            $table->string('path');
            $table->string('url');

            $table->string('thumb_path')->nullable();
            $table->string('thumb_url')->nullable();

            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('extension', 20)->nullable();

            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();

            $table->unsignedInteger('sort_order')->default(100);

            $table->boolean('is_published')->default(false);
            $table->boolean('is_ava')->default(false);

            $table->boolean('is_processed')->default(false);
            $table->string('processing_status')->nullable(); // pending | processing | done | failed
            $table->text('processing_error')->nullable();

            $table->string('poster_path')->nullable();
            $table->string('poster_url')->nullable();

            $table->string('video_mp4_path')->nullable();
            $table->string('video_mp4_url')->nullable();

            $table->string('video_hls_path')->nullable();
            $table->string('video_hls_url')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 12, 3)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['good_id', 'type']);
            $table->index(['good_id', 'is_published']);
            $table->index(['good_id', 'is_ava']);
            $table->index(['processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_media');
    }
};
