<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingCampaign extends Model
{
    protected $fillable = ['name', 'type', 'status', 'subject', 'preheader', 'template_id', 'contact_set_id', 'html_markup', 'plaintext', 'from_email', 'from_name', 'reply_to', 'scheduled_at', 'started_at', 'completed_at', 'created_by', 'updated_by', 'approved_by', 'approved_at', 'compliance_status', 'compliance_note', 'total_recipients', 'accepted_count', 'delivered_count', 'opened_count', 'unique_opened_count', 'clicked_count', 'unique_clicked_count', 'unsubscribed_count', 'soft_bounced_count', 'hard_bounced_count', 'spam_count', 'failed_count', 'metadata', 'tags'];

    protected $casts = ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'approved_at' => 'datetime', 'metadata' => 'array', 'tags' => 'array'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailingTemplate::class, 'template_id');
    }

    public function contactSet(): BelongsTo
    {
        return $this->belongsTo(MailingContactSet::class, 'contact_set_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailingCampaignRecipient::class, 'campaign_id');
    }

    public function offerItems(): HasMany
    {
        return $this->hasMany(MailingOfferItem::class, 'campaign_id')->orderBy('sort_order')->orderBy('id');
    }
}
