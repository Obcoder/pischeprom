<?php

namespace App\Models;

use App\Domain\AiSales\Enums\EntityProposalAction;
use App\Domain\AiSales\Enums\EntityProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class EntityCandidateProposal extends Model
{
    protected $fillable = [
        'unit_id',
        'unit_business_context_id',
        'action',
        'existing_entity_id',
        'entity_name_snapshot',
        'proposed_name',
        'proposed_attributes',
        'evidence_summary',
        'duplicate_candidate_ids',
        'status',
        'proposer_type',
        'proposed_by',
        'reviewed_by',
        'reviewed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'action' => EntityProposalAction::class,
            'status' => EntityProposalStatus::class,
            'proposed_attributes' => 'array',
            'duplicate_candidate_ids' => 'array',
            'reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Entity candidate proposals are retained for review and audit.');
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

    public function existingEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'existing_entity_id');
    }
}
