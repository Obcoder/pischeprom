<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'mail_message_id')) {
                $table->foreignId('mail_message_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('mail_messages')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'mail_message_id')) {
                $table->dropConstrainedForeignId('mail_message_id');
            }
        });
    }
};
