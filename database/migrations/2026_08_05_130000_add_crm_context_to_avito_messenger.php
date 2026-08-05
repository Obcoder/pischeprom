<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_chats', function (Blueprint $table): void {
            $table->foreignId('entity_id')
                ->nullable()
                ->after('avito_messenger_account_id')
                ->constrained('entities')
                ->nullOnDelete();
        });

        Schema::table('avito_messages', function (Blueprint $table): void {
            $table->timestamp('crm_scanned_at')->nullable()->after('last_synced_at')->index();
        });

        Schema::create('avito_contact_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_message_id')
                ->constrained('avito_messages')
                ->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->string('raw_value', 1024);
            $table->string('normalized_value', 512)->nullable();
            $table->char('fingerprint', 64);
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('telephone_id')
                ->nullable()
                ->constrained('telephones')
                ->nullOnDelete();
            $table->foreignId('building_id')
                ->nullable()
                ->constrained('buildings')
                ->nullOnDelete();
            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['avito_message_id', 'fingerprint'],
                'avito_contact_candidates_message_fingerprint_unique'
            );
        });

        Schema::create('avito_chat_order', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('avito_chat_id')
                ->constrained('avito_chats')
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('source_message_id')
                ->nullable()
                ->constrained('avito_messages')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['avito_chat_id', 'order_id']);
            $table->index(['order_id', 'avito_chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avito_chat_order');
        Schema::dropIfExists('avito_contact_candidates');

        Schema::table('avito_messages', function (Blueprint $table): void {
            $table->dropColumn('crm_scanned_at');
        });

        Schema::table('avito_chats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('entity_id');
        });
    }
};
