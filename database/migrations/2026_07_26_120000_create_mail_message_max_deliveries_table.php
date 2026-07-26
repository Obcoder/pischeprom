<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_message_max_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_message_id')
                ->constrained('mail_messages')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('target_type', 16);
            $table->string('target_id', 96);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('text_parts_total')->default(0);
            $table->unsignedSmallInteger('text_parts_sent')->default(0);
            $table->unsignedSmallInteger('attachments_total')->default(0);
            $table->unsignedSmallInteger('attachments_sent')->default(0);
            $table->json('attachment_tokens')->nullable();
            $table->json('skipped_attachments')->nullable();
            $table->json('provider_message_ids')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['mail_message_id', 'target_type', 'target_id'],
                'mail_max_delivery_target_unique'
            );
            $table->index(['target_type', 'target_id'], 'mail_max_delivery_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_max_deliveries');
    }
};
