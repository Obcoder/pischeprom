<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvitoWebhookEvent extends Model
{
    protected $fillable = [
        'deduplication_key',
        'external_event_id',
        'event_type',
        'payload',
        'status',
        'received_at',
        'processed_at',
        'error_message',
    ];

    protected $hidden = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
