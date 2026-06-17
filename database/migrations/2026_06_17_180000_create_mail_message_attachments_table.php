<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_message_attachments')) {
            return;
        }

        Schema::create('mail_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('mail_message_id')
                ->constrained('mail_messages')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('disk')->default('yandex')->index();
            $table->string('path', 1024);
            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('content_id')->nullable();
            $table->string('disposition', 32)->nullable();
            $table->timestamp('saved_to_disk_at')->nullable()->index();

            $table->index(['mail_message_id', 'disk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_attachments');
    }
};
