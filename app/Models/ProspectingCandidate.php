<?php

namespace App\Models;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ProspectingCandidate extends Model
{
    protected $fillable = [
        'public_id', 'prospecting_search_job_id', 'prospecting_search_query_id', 'ai_agent_run_id',
        'purpose', 'lane', 'role_code', 'working_name', 'normalized_name', 'normalized_domain',
        'canonical_website', 'country_id', 'region_id', 'city_id', 'location_display',
        'public_activity_summary', 'relevance_summary', 'confidence_components', 'source_count',
        'fingerprint_hash', 'normalized_payload_hash', 'status', 'resolution_outcome',
        'resolved_unit_id', 'reviewed_by', 'reviewed_at', 'resolution_reason_code',
        'expires_at', 'anonymized_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ProspectingPurpose::class,
            'lane' => BusinessLane::class,
            'role_code' => UnitRoleCode::class,
            'status' => ProspectingCandidateStatus::class,
            'resolution_outcome' => CandidateResolutionOutcome::class,
            'confidence_components' => 'array',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $candidate): void {
            $candidate->public_id ??= (string) Str::uuid();
        });
        static::saving(static function (self $candidate): void {
            $purpose = $candidate->purpose instanceof ProspectingPurpose
                ? $candidate->purpose
                : ProspectingPurpose::from((string) $candidate->purpose);
            $lane = $candidate->lane instanceof BusinessLane ? $candidate->lane : BusinessLane::from((string) $candidate->lane);
            $role = $candidate->role_code instanceof UnitRoleCode ? $candidate->role_code : UnitRoleCode::from((string) $candidate->role_code);
            if ($lane !== $purpose->lane() || $role !== $purpose->role()) {
                throw new LogicException('Candidate lane and role must match its server-owned purpose snapshot.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Prospecting candidates are retained or anonymized, not deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchJob::class, 'prospecting_search_job_id');
    }

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchQuery::class, 'prospecting_search_query_id');
    }

    public function resolvedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'resolved_unit_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ProspectingCandidateSource::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(ProspectingCandidateChannel::class);
    }

    public function unitMatches(): HasMany
    {
        return $this->hasMany(ProspectingCandidateUnitMatch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProspectingCandidateProduct::class);
    }

    public function goodMatches(): HasMany
    {
        return $this->hasMany(UnitGoodMatch::class);
    }
}
