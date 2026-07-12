<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaxMessage extends Model
{
    use HasFactory;

    public const DIRECTION_INCOMING = 'incoming';
    public const DIRECTION_OUTGOING = 'outgoing';

    protected $fillable = [
        'max_chat_id',
        'max_message_id',
        'direction',
        'status',
        'phone_normalized',
        'chat_id',
        'user_id',
        'text',
        'error_message',
        'payload',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function maxChat(): BelongsTo
    {
        return $this->belongsTo(MaxChat::class);
    }
}
