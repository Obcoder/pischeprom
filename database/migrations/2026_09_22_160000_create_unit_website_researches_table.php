<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_website_researches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_mail_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
            $table->foreignId('source_research_id')->nullable()->constrained('mail_message_researches')->nullOnDelete();
            $table->foreignId('saved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('url');
            $table->json('result');
            $table->timestamp('researched_at');
            $table->timestamp('saved_at');
            $table->timestamps();
            $table->unique(['unit_id', 'source_research_id'], 'unit_website_research_source_unique');
            $table->index(['unit_id', 'saved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_website_researches');
    }
};
