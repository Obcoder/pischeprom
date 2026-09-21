<?php

use App\Models\AvitoChat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Repair only states supported by archived read receipts. Unknown
        // incoming messages remain unread; no request or mutation is sent to Avito.
        AvitoChat::withoutEvents(function (): void {
            AvitoChat::query()->select('id')->chunkById(100, function ($chats): void {
                foreach ($chats as $row) {
                    DB::transaction(function () use ($row): void {
                        $chat = AvitoChat::query()->lockForUpdate()->findOrFail($row->id);
                        $chat->messages()->whereNotNull('remote_read_at')->where('is_read', false)
                            ->update(['is_read' => true]);

                        $lastMessage = (array) data_get($chat->payload, 'last_message', []);
                        $readTimestamp = data_get($lastMessage, 'read');
                        if (data_get($lastMessage, 'is_read') === true || (is_numeric($readTimestamp) && $readTimestamp > 0)) {
                            $chat->messages()->where('external_message_id', data_get($lastMessage, 'id'))
                                ->update(['is_read' => true]);
                        }

                        $readThrough = $chat->messages()->where('direction', 'in')->where('is_read', true)
                            ->where('remote_type', '!=', 'system')->where('type', '!=', 'system')
                            ->max('remote_created_at');
                        if ($readThrough) {
                            $chat->messages()->where('direction', 'in')->where('is_read', false)
                                ->where('remote_created_at', '<', $readThrough)->update(['is_read' => true]);
                        }
                        $count = $chat->messages()->where('direction', 'in')->where('is_read', false)
                            ->whereNotIn('remote_type', ['deleted', 'system'])->count();
                        $chat->update(['unread_count' => $count, 'is_unread' => $count > 0]);
                    }, 3);
                }
            });
        });
    }

    public function down(): void
    {
        // Read receipts cannot safely be reverted to the previously corrupt state.
    }
};
