<?php

namespace App\Models;

use App\Models\Concerns\RejectsDeprecatedProviderPayloadWrites;
use Illuminate\Database\Eloquent\Model;

class MailingMessage extends Model
{
    use RejectsDeprecatedProviderPayloadWrites;

    protected $fillable = [
        'provider',
        'campaign_id',
        'campaign_recipient_id',
        'contact_id',
        'email',
        'subject',
        'status',
        'unisender_job_id',
        'provider_message_id',
        'request_hash',
        'response_hash',
        'request_profile',
        'http_status_category',
        'safe_request_id',
        'safe_error_code',
        'safe_summary',
        'ambiguous_acceptance_at',
    ];

    protected $casts = ['ambiguous_acceptance_at' => 'datetime'];

    protected $hidden = ['request_payload', 'response_payload', 'failed_emails', 'error_message'];

    protected function deprecatedProviderPayloadColumns(): array
    {
        return ['request_payload', 'response_payload', 'failed_emails', 'error_message'];
    }
}
