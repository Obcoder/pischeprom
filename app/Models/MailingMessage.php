<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingMessage extends Model
{
    protected $fillable = ['provider', 'campaign_id', 'campaign_recipient_id', 'contact_id', 'email', 'subject', 'status', 'unisender_job_id', 'provider_message_id', 'request_payload', 'response_payload', 'failed_emails', 'error_message'];

    protected $casts = ['request_payload' => 'array', 'response_payload' => 'array', 'failed_emails' => 'array'];
}
