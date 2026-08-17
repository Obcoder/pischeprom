<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutreachDispatchDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'public_id', 'outreach_draft_id', 'outreach_draft_revision_id', 'communication_permission_id',
        'eligible', 'block_reasons', 'decision_hash', 'policy_version', 'evaluated_by', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return ['eligible' => 'boolean', 'block_reasons' => 'array', 'evaluated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Dispatch eligibility decisions are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Dispatch eligibility decisions are append-only.');
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

    public function permission(): BelongsTo
    {
        return $this->belongsTo(CommunicationPermission::class, 'communication_permission_id');
    }
}
