<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoMessengerAccount extends Model
{
    protected $fillable = [
        'avito_connection_id',
        'source_key',
        'external_user_id',
        'name',
        'sync_enabled',
        'sync_status',
        'last_sync_started_at',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'sync_enabled' => 'boolean',
            'last_sync_started_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AvitoConnection::class, 'avito_connection_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(AvitoChat::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(AvitoMessengerSyncRun::class);
    }
}
