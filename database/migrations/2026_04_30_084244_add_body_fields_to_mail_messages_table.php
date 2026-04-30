<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_messages', 'html')) {
                $table->longText('html')->nullable()->after('preview');
            }

            if (!Schema::hasColumn('mail_messages', 'text')) {
                $table->longText('text')->nullable()->after('html');
            }

            if (!Schema::hasColumn('mail_messages', 'body_loaded_at')) {
                $table->timestamp('body_loaded_at')->nullable()->after('text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (Schema::hasColumn('mail_messages', 'body_loaded_at')) {
                $table->dropColumn('body_loaded_at');
            }

            if (Schema::hasColumn('mail_messages', 'text')) {
                $table->dropColumn('text');
            }

            if (Schema::hasColumn('mail_messages', 'html')) {
                $table->dropColumn('html');
            }
        });
    }
};
