<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_message_notes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('mail_message_id')
                ->constrained('mail_messages')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('title')->nullable();
            $table->text('body');
            $table->string('importance', 32)->default('normal')->index();

            $table->index(['mail_message_id', 'importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_notes');
    }
};
