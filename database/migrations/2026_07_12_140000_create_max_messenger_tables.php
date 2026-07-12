<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('max_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 64)->nullable();
            $table->string('phone_normalized', 32)->nullable()->unique();
            $table->string('chat_id', 96)->nullable()->unique();
            $table->string('user_id', 96)->nullable()->index();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('title')->nullable();
            $table->string('source_type', 32)->default('manual')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'unit_id']);
        });

        Schema::create('max_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('max_chat_id')->nullable()->constrained('max_chats')->nullOnDelete();
            $table->string('max_message_id', 128)->nullable()->index();
            $table->string('direction', 16)->default('outgoing')->index();
            $table->string('status', 32)->default('draft')->index();
            $table->string('phone_normalized', 32)->nullable()->index();
            $table->string('chat_id', 96)->nullable()->index();
            $table->string('user_id', 96)->nullable()->index();
            $table->text('text')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('max_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('update_id', 128)->nullable()->index();
            $table->string('update_type', 64)->nullable()->index();
            $table->string('phone_normalized', 32)->nullable()->index();
            $table->string('chat_id', 96)->nullable()->index();
            $table->string('user_id', 96)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('max_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('url', 512);
            $table->string('secret', 255)->nullable();
            $table->json('update_types')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->json('provider_response')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('max_subscriptions');
        Schema::dropIfExists('max_webhook_events');
        Schema::dropIfExists('max_messages');
        Schema::dropIfExists('max_chats');
    }
};
