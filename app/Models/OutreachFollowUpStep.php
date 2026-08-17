<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutreachFollowUpStep extends Model
{
    protected $fillable = [
        'outreach_follow_up_plan_id', 'position', 'status', 'earliest_at', 'required_reviews',
        'outreach_draft_id', 'outreach_draft_revision_id', 'safe_reason_code',
    ];

    protected function casts(): array
    {
        return ['status' => OutreachFollowUpStatus::class, 'earliest_at' => 'datetime', 'required_reviews' => 'array'];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Outreach follow-up steps cannot be deleted.');
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OutreachFollowUpPlan::class, 'outreach_follow_up_plan_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class, 'outreach_draft_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OutreachDraftRevision::class, 'outreach_draft_revision_id');
    }
}
