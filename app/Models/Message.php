<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'chat_id',
        'message_id',
        'date',
        'update_id',
    ];
    protected $with = [
        'chat',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class)
            ->withDefault();
    }
}
