<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoMessage extends Model
{
    protected $fillable = [
        'avito_chat_id',
        'external_message_id',
        'author_id',
        'direction',
        'type',
        'remote_type',
        'text',
        'is_read',
        'remote_created_at',
        'remote_read_at',
        'deleted_from_avito_at',
        'last_synced_at',
        'content',
        'quote',
        'payload',
    ];

    protected $hidden = ['content', 'quote', 'payload'];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'remote_created_at' => 'datetime',
            'remote_read_at' => 'datetime',
            'deleted_from_avito_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'content' => 'encrypted:array',
            'quote' => 'encrypted:array',
            'payload' => 'encrypted:array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AvitoChat::class, 'avito_chat_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AvitoMessageAttachment::class);
    }
}
