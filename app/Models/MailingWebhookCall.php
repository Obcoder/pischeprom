<?php

namespace App\Models;

use App\Models\Concerns\RejectsDeprecatedProviderPayloadWrites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingWebhookCall extends Model
{
    use RejectsDeprecatedProviderPayloadWrites;

    public $timestamps = false;

    protected $fillable = [
        'provider',
        'auth_valid',
        'request_hash',
        'events_count',
        'status',
        'safe_error_code',
        'safe_summary',
        'verified_at',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'auth_valid' => 'boolean',
        'verified_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $hidden = ['raw_payload', 'parsed_payload', 'error_message'];

    public function events(): HasMany
    {
        return $this->hasMany(MailingEvent::class, 'webhook_call_id');
    }

    protected function deprecatedProviderPayloadColumns(): array
    {
        return ['raw_payload', 'parsed_payload', 'error_message'];
    }
}
