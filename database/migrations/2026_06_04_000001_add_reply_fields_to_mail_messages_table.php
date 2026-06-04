<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_messages', 'reply_to_mail_message_id')) {
                $table
                    ->foreignId('reply_to_mail_message_id')
                    ->nullable()
                    ->after('message_id')
                    ->constrained('mail_messages')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('mail_messages', 'in_reply_to')) {
                $table->string('in_reply_to')->nullable()->after('reply_to_mail_message_id')->index();
            }

            if (!Schema::hasColumn('mail_messages', 'references')) {
                $table->text('references')->nullable()->after('in_reply_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            if (Schema::hasColumn('mail_messages', 'reply_to_mail_message_id')) {
                $table->dropConstrainedForeignId('reply_to_mail_message_id');
            }

            if (Schema::hasColumn('mail_messages', 'in_reply_to')) {
                $table->dropColumn('in_reply_to');
            }

            if (Schema::hasColumn('mail_messages', 'references')) {
                $table->dropColumn('references');
            }
        });
    }
};
