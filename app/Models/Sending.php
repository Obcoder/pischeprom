<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sending extends Model
{
    protected $fillable = [
        'email_id',
        'subject',
        'status',
        'provider',
        'provider_message_id',
        'tracking_token',
        'opens_count',
        'clicks_count',
        'sent_at',
        'opened_at',
        'last_clicked_at',
        'error',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sending $sending) {
            if (!$sending->tracking_token) {
                $sending->tracking_token = Str::uuid()->toString();
            }
        });
    }

    public function email()
    {
        return $this->belongsTo(Email::class);
    }
}
