<?php

use App\Services\Mail\LegacySentIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Older UnitMailController used UUID@app-host without angle brackets.
        // Only classify historical records; never turn uncertain delivery into sent.
        DB::table('mail_messages')->where('direction', 'outgoing')->whereNull('imap_uid')
            ->where('delivery_status', 'sent')->whereNull('sent_mime_path')->whereNull('smtp_accepted_at')
            ->whereNull('sent_copy_status')->where('is_reconstructed', false)
            ->select(['id', 'message_id'])->orderBy('id')->chunkById(200, function ($rows): void {
                $ids = $rows->filter(fn ($row) => LegacySentIdentity::matches($row->message_id))->pluck('id');
                if ($ids->isNotEmpty()) {
                    DB::table('mail_messages')->whereIn('id', $ids)->whereNull('sent_copy_status')
                        ->update(['sent_copy_status' => 'legacy']);
                }
            });
    }

    public function down(): void
    {
        // Classification preserves historical facts; linked/reconstructed copies must not be undone.
    }
};
