<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutreachDraftReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'outreach_draft_id', 'outreach_draft_revision_id', 'review_type', 'decision', 'reason_code',
        'safe_note', 'decision_hash', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'review_type' => OutreachReviewType::class,
            'decision' => OutreachReviewDecision::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Outreach reviews are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Outreach reviews are append-only.');
        });
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
