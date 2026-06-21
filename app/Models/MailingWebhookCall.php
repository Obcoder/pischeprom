<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingWebhookCall extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider', 'auth_valid', 'raw_payload', 'parsed_payload', 'events_count', 'processed_at', 'error_message', 'created_at'];

    protected $casts = ['auth_valid' => 'boolean', 'parsed_payload' => 'array', 'processed_at' => 'datetime', 'created_at' => 'datetime'];
}
