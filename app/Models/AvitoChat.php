<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoChat extends Model
{
    protected $fillable = [
        'avito_messenger_account_id',
        'entity_id',
        'external_chat_id',
        'chat_type',
        'context_type',
        'context_id',
        'title',
        'context_url',
        'peer_user_id',
        'peer_name',
        'peer_avatar_url',
        'last_message_id',
        'last_message_type',
        'last_message_preview',
        'is_unread',
        'unread_count',
        'remote_created_at',
        'remote_updated_at',
        'last_message_at',
        'last_synced_at',
        'payload',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'is_unread' => 'boolean',
            'remote_created_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'payload' => 'encrypted:array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AvitoMessengerAccount::class, 'avito_messenger_account_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AvitoMessage::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'avito_chat_order')
            ->withPivot('source_message_id')
            ->withTimestamps();
    }

    public function messageTemplateUsages(): HasMany
    {
        return $this->hasMany(AvitoMessageTemplateUsage::class);
    }
}
