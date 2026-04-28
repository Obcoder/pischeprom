<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_mail_message', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('email_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('mail_message_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('role', ['from', 'to', 'cc'])->index();

            $table->unique(['email_id', 'mail_message_id', 'role']);
            $table->index(['email_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_mail_message');
    }
};
