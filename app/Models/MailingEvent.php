<?php

namespace App\Models;

use App\Models\Concerns\RejectsDeprecatedProviderPayloadWrites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingEvent extends Model
{
    use RejectsDeprecatedProviderPayloadWrites;

    public $timestamps = false;

    protected $fillable = [
        'webhook_call_id',
        'provider',
        'event_fingerprint',
        'provider_event_id',
        'campaign_id',
        'campaign_recipient_id',
        'contact_id',
        'unisender_job_id',
        'provider_message_id',
        'mailing_message_id',
        'sending_id',
        'mail_message_id',
        'event_name',
        'normalized_event_type',
        'status',
        'normalized_status',
        'event_time',
        'verified_at',
        'processed_at',
        'safe_error_code',
        'safe_summary',
        'created_at',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $hidden = [
        'email',
        'url',
        'delivery_status',
        'destination_response',
        'user_agent',
        'ip',
        'country',
        'city',
        'sender_ip',
        'metadata',
        'payload',
    ];

    public function webhookCall(): BelongsTo
    {
        return $this->belongsTo(MailingWebhookCall::class, 'webhook_call_id');
    }

    public function mailingMessage(): BelongsTo
    {
        return $this->belongsTo(MailingMessage::class);
    }

    protected function deprecatedProviderPayloadColumns(): array
    {
        return [
            'email',
            'url',
            'delivery_status',
            'destination_response',
            'user_agent',
            'ip',
            'country',
            'city',
            'sender_ip',
            'metadata',
            'payload',
        ];
    }
}
