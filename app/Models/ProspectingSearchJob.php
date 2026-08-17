<?php

namespace App\Models;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ProspectingSearchJob extends Model
{
    protected $fillable = [
        'public_id', 'created_by', 'owner_user_id', 'reviewer_user_id', 'purpose', 'lane',
        'default_role_code', 'primary_good_id', 'country_id', 'region_id', 'city_id', 'locale',
        'max_queries', 'max_candidates', 'max_results_per_query', 'max_rows', 'max_bytes',
        'max_searches', 'max_cost_rub', 'safe_objective', 'criteria_snapshot', 'policy_version',
        'workflow_version', 'schema_hash', 'status', 'auto_create_unit', 'retention_profile',
        'product_mapping_state', 'product_mapping_reason_code',
        'approved_by', 'approved_at', 'ai_agent_run_id', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ProspectingPurpose::class,
            'lane' => BusinessLane::class,
            'default_role_code' => UnitRoleCode::class,
            'status' => ProspectingJobStatus::class,
            'product_mapping_state' => ProductMappingState::class,
            'criteria_snapshot' => 'array',
            'auto_create_unit' => 'boolean',
            'max_cost_rub' => 'decimal:4',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $job): void {
            $job->public_id ??= (string) Str::uuid();
        });
        static::saving(static function (self $job): void {
            $purpose = $job->purpose instanceof ProspectingPurpose
                ? $job->purpose
                : ProspectingPurpose::from((string) $job->purpose);

            $lane = $job->lane instanceof BusinessLane ? $job->lane : BusinessLane::from((string) $job->lane);
            $role = $job->default_role_code instanceof UnitRoleCode ? $job->default_role_code : UnitRoleCode::from((string) $job->default_role_code);
            if ($lane !== $purpose->lane() || $role !== $purpose->role()) {
                throw new LogicException('Prospecting lane and role are server-owned derivatives of purpose.');
            }

            if ($job->auto_create_unit) {
                throw new LogicException('Automatic Unit creation is disabled in Stage 08.');
            }
            if ((int) $job->max_searches !== 0 || (float) $job->max_cost_rub !== 0.0) {
                throw new LogicException('Stage 08 prospecting jobs cannot reserve searches or provider cost.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Prospecting jobs are archived, not deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class, 'prospecting_search_job_goods')
            ->withPivot(['role', 'source_origin', 'compatibility_state'])->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'prospecting_search_job_products')
            ->withPivot(['role', 'source_origin'])->withTimestamps();
    }

    public function queries(): HasMany
    {
        return $this->hasMany(ProspectingSearchQuery::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ProspectingCandidate::class);
    }

    public function searchExecutions(): HasMany
    {
        return $this->hasMany(ProspectingSearchExecution::class);
    }

    public function searchResults(): HasMany
    {
        return $this->hasMany(ProspectingSearchResult::class);
    }
}
