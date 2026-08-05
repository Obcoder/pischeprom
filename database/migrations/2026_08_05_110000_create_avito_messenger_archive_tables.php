<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_messenger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_connection_id')->nullable()->constrained('avito_connections')->nullOnDelete();
            $table->string('source_key', 80)->unique();
            $table->string('external_user_id', 80)->nullable()->index();
            $table->string('name')->nullable();
            $table->boolean('sync_enabled')->default(true)->index();
            $table->string('sync_status', 32)->default('idle')->index();
            $table->timestamp('last_sync_started_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();
        });

        Schema::create('avito_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_messenger_account_id')->constrained('avito_messenger_accounts')->cascadeOnDelete();
            $table->string('external_chat_id', 160);
            $table->string('chat_type', 16)->nullable()->index();
            $table->string('context_type', 32)->nullable();
            $table->string('context_id', 100)->nullable()->index();
            $table->string('title')->nullable();
            $table->text('context_url')->nullable();
            $table->string('peer_user_id', 80)->nullable()->index();
            $table->string('peer_name')->nullable();
            $table->text('peer_avatar_url')->nullable();
            $table->string('last_message_id', 160)->nullable();
            $table->string('last_message_type', 32)->nullable();
            $table->text('last_message_preview')->nullable();
            $table->boolean('is_unread')->default(false)->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable()->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();

            $table->unique(['avito_messenger_account_id', 'external_chat_id'], 'avito_chats_account_external_unique');
        });

        Schema::create('avito_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_chat_id')->constrained('avito_chats')->cascadeOnDelete();
            $table->string('external_message_id', 160);
            $table->string('author_id', 80)->nullable()->index();
            $table->string('direction', 8)->nullable()->index();
            $table->string('type', 32)->default('unknown')->index();
            $table->string('remote_type', 32)->default('unknown')->index();
            $table->text('text')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('remote_created_at')->nullable()->index();
            $table->timestamp('remote_read_at')->nullable();
            $table->timestamp('deleted_from_avito_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->longText('content')->nullable();
            $table->longText('quote')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamps();

            $table->unique(['avito_chat_id', 'external_message_id'], 'avito_messages_chat_external_unique');
        });

        Schema::create('avito_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_message_id')->constrained('avito_messages')->cascadeOnDelete();
            $table->string('kind', 24)->index();
            $table->string('external_id', 190)->nullable();
            $table->text('remote_url')->nullable();
            $table->string('storage_disk', 48)->nullable();
            $table->text('storage_path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('archive_attempts')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('archive_error')->nullable();
            $table->timestamps();

            $table->unique(['avito_message_id', 'kind'], 'avito_message_attachments_message_kind_unique');
        });

        Schema::create('avito_messenger_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avito_messenger_account_id')->nullable()->constrained('avito_messenger_accounts')->nullOnDelete();
            $table->string('status', 32)->default('queued')->index();
            $table->boolean('full_sync')->default(false);
            $table->unsignedInteger('chats_seen')->default(0);
            $table->unsignedInteger('chats_created')->default(0);
            $table->unsignedInteger('messages_seen')->default(0);
            $table->unsignedInteger('messages_created')->default(0);
            $table->unsignedInteger('attachments_archived')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_messenger_sync_runs');
        Schema::dropIfExists('avito_message_attachments');
        Schema::dropIfExists('avito_messages');
        Schema::dropIfExists('avito_chats');
        Schema::dropIfExists('avito_messenger_accounts');
    }
};
