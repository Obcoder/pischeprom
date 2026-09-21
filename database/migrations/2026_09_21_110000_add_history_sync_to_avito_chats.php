<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_chats', function (Blueprint $table): void {
            // A completed pass through the history exposed by Avito, not a
            // guarantee that Avito still exposes the whole original chat.
            $table->timestamp('history_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('avito_chats', fn (Blueprint $table) => $table->dropColumn('history_synced_at'));
    }
};
