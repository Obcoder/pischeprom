<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_message_researches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mail_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('input_hash', 64);
            $table->text('query');
            $table->json('result');
            $table->timestamps();
            $table->unique(['mail_message_id', 'kind', 'input_hash'], 'mail_research_input_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_researches');
    }
};
