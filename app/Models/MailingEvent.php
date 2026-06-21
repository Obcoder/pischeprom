<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider', 'event_fingerprint', 'campaign_id', 'campaign_recipient_id', 'contact_id', 'unisender_job_id', 'email', 'event_name', 'status', 'event_time', 'url', 'delivery_status', 'destination_response', 'user_agent', 'ip', 'country', 'city', 'sender_ip', 'metadata', 'payload', 'created_at'];

    protected $casts = ['event_time' => 'datetime', 'metadata' => 'array', 'payload' => 'array', 'created_at' => 'datetime'];
}
