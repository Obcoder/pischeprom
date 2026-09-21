<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_chats', function (Blueprint $table): void {
            $table->timestamp('waiting_since')->nullable();
            $table->text('waiting_note')->nullable();
            $table->index(['waiting_since', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('avito_chats', function (Blueprint $table): void {
            $table->dropIndex(['waiting_since', 'id']);
            $table->dropColumn(['waiting_since', 'waiting_note']);
        });
    }
};
