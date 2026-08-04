<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaxWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'update_id',
        'deduplication_key',
        'update_type',
        'phone_normalized',
        'chat_id',
        'user_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
