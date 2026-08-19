<?php

namespace App\Models;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignCadence;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class ClientAcquisitionCampaign extends Model
{
    protected $table = 'ai_sales_campaigns';

    protected $fillable = [
        'public_id', 'safe_name', 'created_by', 'owner_user_id', 'reviewer_user_id', 'originating_good_id',
        'purpose', 'lane', 'role_code', 'status', 'automation_mode', 'safe_objective', 'criteria_snapshot',
        'product_scope_hash', 'criteria_geography_hash', 'workflow_code', 'workflow_version', 'workflow_hash',
        'policy_version', 'policy_hash', 'disclosure_policy_hash', 'schedule_cadence', 'schedule_timezone',
        'next_run_at', 'last_run_at', 'max_active_runs', 'max_runs_per_day', 'max_runs_per_month',
        'max_search_requests_per_run', 'max_search_requests_per_day', 'max_search_requests_per_month',
        'max_research_pages_per_run', 'max_candidates_per_run', 'max_units_per_run', 'max_units_per_day',
        'max_units_per_month', 'max_drafts_per_run', 'max_drafts_per_day', 'max_drafts_per_month',
        'max_requests_per_run', 'max_requests_per_day', 'max_requests_per_month', 'max_tokens_per_run',
        'max_tokens_per_day', 'max_tokens_per_month', 'max_cost_rub_per_run', 'max_cost_rub_per_day',
        'max_cost_rub_per_month', 'auto_unit_policy_code', 'auto_unit_policy_version', 'auto_unit_approved',
        'auto_draft_policy_code', 'auto_draft_policy_version', 'auto_draft_approved', 'approval_snapshot_hash',
        'approved_by', 'approved_at', 'paused_by', 'paused_at', 'completed_at', 'cancelled_at',
        'last_block_code', 'safe_status_summary', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ProspectingPurpose::class,
            'lane' => BusinessLane::class,
            'role_code' => UnitRoleCode::class,
            'status' => ClientAcquisitionCampaignStatus::class,
            'automation_mode' => ClientAcquisitionAutomationMode::class,
            'schedule_cadence' => ClientAcquisitionCampaignCadence::class,
            'criteria_snapshot' => 'array',
            'auto_unit_approved' => 'boolean',
            'auto_draft_approved' => 'boolean',
            'max_cost_rub_per_run' => 'decimal:4',
            'max_cost_rub_per_day' => 'decimal:4',
            'max_cost_rub_per_month' => 'decimal:4',
            'next_run_at' => 'datetime', 'last_run_at' => 'datetime', 'approved_at' => 'datetime',
            'paused_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $campaign): void {
            $campaign->public_id ??= (string) Str::uuid();
        });
        static::saving(static function (self $campaign): void {
            $purpose = $campaign->purpose instanceof ProspectingPurpose
                ? $campaign->purpose : ProspectingPurpose::from((string) $campaign->purpose);
            $lane = $campaign->lane instanceof BusinessLane
                ? $campaign->lane : BusinessLane::from((string) $campaign->lane);
            $role = $campaign->role_code instanceof UnitRoleCode
                ? $campaign->role_code : UnitRoleCode::from((string) $campaign->role_code);
            if ($purpose !== ProspectingPurpose::BuyerDiscovery
                || $lane !== BusinessLane::Sales
                || $role !== UnitRoleCode::ProspectiveCustomer) {
                throw new LogicException('Campaign purpose, lane and role are fixed server-owned V1 values.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Client acquisition campaigns are archived, not deleted.');
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function originatingGood(): BelongsTo
    {
        return $this->belongsTo(Good::class, 'originating_good_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'ai_sales_campaign_products', 'ai_sales_campaign_id')
            ->withPivot(['role', 'source_origin'])->withTimestamps();
    }

    public function runLinks(): HasMany
    {
        return $this->hasMany(ClientAcquisitionCampaignRunLink::class, 'ai_sales_campaign_id');
    }
}
