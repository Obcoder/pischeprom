<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class OutreachDispatch extends Model
{
    protected $fillable = [
        'public_id', 'unit_id', 'unit_business_context_id', 'outreach_draft_id',
        'outreach_draft_revision_id', 'communication_permission_id', 'unit_contact_context_link_id',
        'unit_product_match_id', 'unit_good_match_id', 'mail_message_id', 'sending_id', 'purpose',
        'state', 'request_profile', 'idempotency_hash', 'revision_hash', 'renderer_hash', 'dlp_hash',
        'evidence_hash', 'permission_scope_hash', 'sender_config_hash', 'unsubscribe_token_hash',
        'last_revalidation_hash', 'last_block_reason', 'safe_summary', 'provider_job_id',
        'prepared_by', 'queued_by', 'prepared_at', 'last_revalidated_at', 'queue_requested_at',
        'queued_at', 'provider_accepted_at', 'sent_at', 'delivered_at', 'replied_at', 'cancelled_at',
        'failed_at', 'ambiguous_acceptance_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => MessagePurpose::class,
            'state' => OutreachDispatchState::class,
            'prepared_at' => 'datetime', 'last_revalidated_at' => 'datetime',
            'queue_requested_at' => 'datetime', 'queued_at' => 'datetime',
            'provider_accepted_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime',
            'replied_at' => 'datetime', 'cancelled_at' => 'datetime', 'failed_at' => 'datetime',
            'ambiguous_acceptance_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Outreach dispatches use an audited lifecycle and cannot be deleted.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class, 'outreach_draft_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OutreachDraftRevision::class, 'outreach_draft_revision_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(CommunicationPermission::class, 'communication_permission_id');
    }

    public function contactLink(): BelongsTo
    {
        return $this->belongsTo(UnitContactContextLink::class, 'unit_contact_context_link_id');
    }

    public function productMatch(): BelongsTo
    {
        return $this->belongsTo(UnitProductMatch::class, 'unit_product_match_id');
    }

    public function goodMatch(): BelongsTo
    {
        return $this->belongsTo(UnitGoodMatch::class, 'unit_good_match_id');
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function sending(): BelongsTo
    {
        return $this->belongsTo(Sending::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function queuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(OutreachDispatchDecision::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(OutreachReplyLink::class);
    }

    public function followUpPlan(): HasOne
    {
        return $this->hasOne(OutreachFollowUpPlan::class);
    }
}
