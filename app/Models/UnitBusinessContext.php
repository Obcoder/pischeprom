<?php

namespace App\Models;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class UnitBusinessContext extends Model
{
    protected $fillable = [
        'unit_id',
        'role_code',
        'lane',
        'stage',
        'status',
        'confidence',
        'owner_user_id',
        'reviewer_user_id',
        'primary_good_id',
        'primary_segment',
        'source',
        'first_activity_at',
        'last_activity_at',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'role_code' => UnitRoleCode::class,
            'lane' => BusinessLane::class,
            'stage' => UnitContextStage::class,
            'status' => UnitContextStatus::class,
            'confidence' => 'integer',
            'first_activity_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Unit business contexts are archived, not deleted.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function marketRole(): BelongsTo
    {
        return $this->belongsTo(MarketRole::class, 'role_code', 'code');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function primaryGood(): BelongsTo
    {
        return $this->belongsTo(Good::class, 'primary_good_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(UnitSource::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(UnitObservation::class);
    }

    public function entityCandidateProposals(): HasMany
    {
        return $this->hasMany(EntityCandidateProposal::class);
    }

    public function aiAgentRuns(): HasMany
    {
        return $this->hasMany(AiAgentRun::class);
    }

    public function goodMatches(): HasMany
    {
        return $this->hasMany(UnitGoodMatch::class);
    }

    public function productMatches(): HasMany
    {
        return $this->hasMany(UnitProductMatch::class);
    }

    public function prospectPrioritySnapshots(): HasMany
    {
        return $this->hasMany(UnitProspectPrioritySnapshot::class);
    }

    public function outreachDrafts(): HasMany
    {
        return $this->hasMany(OutreachDraft::class);
    }

    public function communicationPermissions(): HasMany
    {
        return $this->hasMany(CommunicationPermission::class);
    }

    public function communicationSuppressions(): HasMany
    {
        return $this->hasMany(CommunicationSuppression::class);
    }
}
