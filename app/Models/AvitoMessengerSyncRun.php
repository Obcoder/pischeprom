<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoMessengerSyncRun extends Model
{
    protected $fillable = [
        'avito_messenger_account_id',
        'status',
        'full_sync',
        'chats_seen',
        'chats_created',
        'messages_seen',
        'messages_created',
        'attachments_archived',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'full_sync' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AvitoMessengerAccount::class, 'avito_messenger_account_id');
    }
}
