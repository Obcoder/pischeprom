<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('mailbox')->index();
            $table->string('folder')->index();
            $table->enum('direction', ['incoming', 'outgoing'])->index();

            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('message_id')->nullable()->index();

            $table->string('subject')->nullable();
            $table->timestamp('message_date')->nullable()->index();

            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();

            $table->json('to')->nullable();
            $table->json('cc')->nullable();

            $table->text('preview')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->longText('raw_headers')->nullable();

            $table->unique(['mailbox', 'folder', 'imap_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
