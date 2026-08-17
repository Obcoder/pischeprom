<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use App\Domain\AiSales\Outreach\Enums\OutreachDraftStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachGenerationOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OutreachDraft extends Model
{
    protected $fillable = [
        'public_id', 'unit_id', 'unit_business_context_id', 'unit_contact_context_link_id', 'email_id',
        'unit_product_match_id', 'unit_good_match_id', 'product_relevance_snapshot_id', 'good_fit_snapshot_id',
        'prospect_priority_snapshot_id', 'purpose', 'status', 'generation_origin', 'template_profile',
        'template_version', 'template_hash', 'policy_hash', 'input_hash', 'evidence_hash',
        'current_revision_number', 'expires_at', 'lock_version', 'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => MessagePurpose::class,
            'status' => OutreachDraftStatus::class,
            'generation_origin' => OutreachGenerationOrigin::class,
            'expires_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Outreach drafts use an audited lifecycle and cannot be deleted.');
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

    public function contactLink(): BelongsTo
    {
        return $this->belongsTo(UnitContactContextLink::class, 'unit_contact_context_link_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function productMatch(): BelongsTo
    {
        return $this->belongsTo(UnitProductMatch::class, 'unit_product_match_id');
    }

    public function goodMatch(): BelongsTo
    {
        return $this->belongsTo(UnitGoodMatch::class, 'unit_good_match_id');
    }

    public function productRelevanceSnapshot(): BelongsTo
    {
        return $this->belongsTo(UnitProductRelevanceSnapshot::class);
    }

    public function goodFitSnapshot(): BelongsTo
    {
        return $this->belongsTo(UnitGoodFitSnapshot::class);
    }

    public function prospectPrioritySnapshot(): BelongsTo
    {
        return $this->belongsTo(UnitProspectPrioritySnapshot::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OutreachDraftRevision::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(OutreachDraftReview::class);
    }

    public function dispatchDecisions(): HasMany
    {
        return $this->hasMany(OutreachDispatchDecision::class);
    }

    public function currentRevision(): ?OutreachDraftRevision
    {
        if ($this->current_revision_number < 1) {
            return null;
        }

        return $this->revisions()->where('revision_number', $this->current_revision_number)->first();
    }
}
