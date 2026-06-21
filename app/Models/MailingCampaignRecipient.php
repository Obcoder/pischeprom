<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MailingCampaignRecipient extends Model
{
    protected $fillable = ['campaign_id', 'contact_id', 'email', 'normalized_email', 'status', 'skip_reason', 'substitutions', 'metadata', 'personal_html_markup', 'personal_plaintext', 'unsubscribe_token', 'unisender_job_id', 'idempotence_key', 'sent_at', 'delivered_at', 'first_opened_at', 'last_opened_at', 'open_count', 'first_clicked_at', 'last_clicked_at', 'click_count', 'last_clicked_url', 'unsubscribed_at', 'soft_bounced_at', 'hard_bounced_at', 'spam_at', 'failure_reason', 'delivery_info'];

    protected $casts = ['substitutions' => 'array', 'metadata' => 'array', 'delivery_info' => 'array', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'first_opened_at' => 'datetime', 'last_opened_at' => 'datetime', 'first_clicked_at' => 'datetime', 'last_clicked_at' => 'datetime', 'unsubscribed_at' => 'datetime', 'soft_bounced_at' => 'datetime', 'hard_bounced_at' => 'datetime', 'spam_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (MailingCampaignRecipient $recipient): void {
            $recipient->normalized_email = MailingContact::normalizeEmail($recipient->normalized_email ?: $recipient->email);
            if (! $recipient->unsubscribe_token) {
                $recipient->unsubscribe_token = Str::random(48);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailingCampaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(MailingContact::class, 'contact_id');
    }
}
