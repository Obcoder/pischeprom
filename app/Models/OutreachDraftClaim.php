<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutreachDraftClaim extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'outreach_draft_revision_id', 'claim_type', 'text_fragment_hash', 'evidence_type',
        'evidence_reference', 'evidence_hash', 'evidence_status', 'confidence', 'fresh_until',
        'review_status', 'safe_rationale', 'reviewed_by', 'reviewed_at', 'audit_hash',
    ];

    protected function casts(): array
    {
        return ['fresh_until' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Outreach claims are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Outreach claims are append-only.');
        });
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(OutreachDraftRevision::class, 'outreach_draft_revision_id');
    }
}
