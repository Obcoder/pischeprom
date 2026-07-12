<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'max_chat_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('max_chat_id', 64)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'max_chat_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('max_chat_id');
        });
    }
};
