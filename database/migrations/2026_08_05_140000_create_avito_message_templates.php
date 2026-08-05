<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avito_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('system_key', 64)->nullable()->unique();
            $table->string('name', 160);
            $table->string('category', 40)->default('general')->index();
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_favorite')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_favorite', 'sort_order'], 'avito_message_templates_picker_index');
        });

        Schema::create('avito_message_template_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_message_template_id')
                ->constrained('avito_message_templates')
                ->cascadeOnDelete();
            $table->foreignId('avito_chat_id')
                ->constrained('avito_chats')
                ->cascadeOnDelete();
            $table->foreignId('avito_message_id')
                ->nullable()
                ->constrained('avito_messages')
                ->nullOnDelete();
            $table->string('mode', 24)->default('direct');
            $table->longText('rendered_body');
            $table->longText('context')->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();

            $table->index(
                ['avito_message_template_id', 'sent_at'],
                'avito_message_template_usages_template_sent_index'
            );
            $table->index(['avito_chat_id', 'sent_at'], 'avito_message_template_usages_chat_sent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_message_template_usages');
        Schema::dropIfExists('avito_message_templates');
    }
};
