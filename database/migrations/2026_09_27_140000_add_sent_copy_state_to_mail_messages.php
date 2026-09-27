<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->string('delivery_status', 24)->default('sent');
            $table->string('sent_copy_status', 24)->nullable()->index();
            $table->string('sent_copy_error', 64)->nullable();
            $table->string('sent_mime_path')->nullable();
            $table->timestamp('smtp_accepted_at')->nullable();
            $table->boolean('is_reconstructed')->default(false);
        });
        Schema::table('authorized_mail_dispatch_attempts', function (Blueprint $table): void {
            $table->foreignId('mail_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
        });
        DB::table('mail_messages')->where('direction', 'outgoing')->whereNull('imap_uid')
            ->where('message_id', 'like', '%@local.pischeprom>')->update(['sent_copy_status' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('authorized_mail_dispatch_attempts', fn (Blueprint $table) => $table->dropConstrainedForeignId('mail_message_id'));
        Schema::table('mail_messages', fn (Blueprint $table) => $table->dropColumn([
            'delivery_status', 'sent_copy_status', 'sent_copy_error', 'sent_mime_path', 'smtp_accepted_at', 'is_reconstructed',
        ]));
    }
};
