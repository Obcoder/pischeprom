<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Sending extends Model
{
    protected $fillable = [
        'email_id',
        'subject',
        'html',
        'text',
        'provider',
        'provider_message_id',
        'tracking_token',
        'status',
        'sent_at',
        'opened_at',
        'opens_count',
        'clicked_at',
        'click_count',
        'clicks_count',
        'last_clicked_at',
        'last_open_ip',
        'last_open_ua',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
        'opens_count' => 'integer',
        'click_count' => 'integer',
        'clicks_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sending $sending) {
            if (!$sending->tracking_token) {
                $sending->tracking_token = Str::uuid()->toString();
            }
        });
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
