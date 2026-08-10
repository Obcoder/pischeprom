<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_autoload_feeds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('avito_account_id')->unique();
            $table->foreignId('avito_connection_id')
                ->nullable()
                ->constrained('avito_connections')
                ->nullOnDelete();
            $table->string('name', 120)->default('ameise-goods');
            $table->longText('access_token');
            $table->string('profile_status', 32)->default('not_checked')->index();
            $table->longText('defaults')->nullable();
            $table->longText('profile_snapshot')->nullable();
            $table->longText('last_upload_snapshot')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('profile_checked_at')->nullable();
            $table->timestamp('profile_attached_at')->nullable();
            $table->timestamp('last_upload_requested_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_autoload_feed_id')
                ->constrained('avito_autoload_feeds')
                ->cascadeOnDelete();
            $table->foreignId('good_id')
                ->nullable()
                ->constrained('goods')
                ->nullOnDelete();
            $table->foreignId('avito_connection_id')
                ->nullable()
                ->constrained('avito_connections')
                ->nullOnDelete();
            $table->unsignedBigInteger('avito_account_id');
            $table->unsignedBigInteger('avito_item_id')->nullable();
            $table->string('external_id', 80)->unique();
            $table->string('status', 40)->default('draft')->index();
            $table->boolean('draft_dirty')->default(true);
            $table->string('category_node_slug', 180)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->longText('draft_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->longText('last_remote_report')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_upload_requested_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(
                ['avito_account_id', 'status', 'updated_at'],
                'avito_publications_account_status_updated_index'
            );
            $table->index(
                ['avito_account_id', 'avito_item_id'],
                'avito_publications_account_item_index'
            );
        });

        Schema::create('avito_publication_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_publication_id')
                ->constrained('avito_publications')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 32)->default('approved')->index();
            $table->boolean('is_current')->default(true)->index();
            $table->json('selected_fields');
            $table->longText('source_snapshot');
            $table->longText('payload_snapshot');
            $table->longText('remote_report')->nullable();
            $table->timestamp('approved_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['avito_publication_id', 'version'],
                'avito_publication_revisions_publication_version_unique'
            );
        });

        Schema::create('avito_publication_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_publication_revision_id')
                ->constrained('avito_publication_revisions')
                ->cascadeOnDelete();
            $table->foreignId('good_media_id')
                ->nullable()
                ->constrained('good_media')
                ->nullOnDelete();
            $table->string('disk', 40)->default('avito');
            $table->string('path', 1000);
            $table->string('file_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('title', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['avito_publication_revision_id', 'sort_order'],
                'avito_publication_media_revision_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_publication_media');
        Schema::dropIfExists('avito_publication_revisions');
        Schema::dropIfExists('avito_publications');
        Schema::dropIfExists('avito_autoload_feeds');
    }
};
