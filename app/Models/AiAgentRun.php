<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiTaskProfile;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentRun extends Model
{
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected $fillable = [
        'public_id', 'ai_agent_definition_id', 'definition_code', 'definition_version',
        'initiator_user_id', 'unit_id', 'unit_name_snapshot', 'unit_business_context_id',
        'unit_context_snapshot', 'purpose', 'audience', 'lane', 'role_code', 'task_profile',
        'requested_contour', 'selected_contour', 'provider_route_preference',
        'model_profile_preference', 'actual_provider', 'actual_route', 'actual_model', 'status',
        'policy_decision_hash', 'prompt_hash', 'schema_hash', 'safe_input_summary',
        'safe_input_hash', 'max_steps', 'max_searches', 'max_tokens', 'max_cost_rub',
        'accumulated_tokens', 'accumulated_searches', 'accumulated_cost_rub', 'current_step',
        'lock_version', 'idempotency_key', 'correlation_id', 'safe_error_code',
        'safe_error_summary', 'queued_at', 'prepared_at', 'started_at', 'completed_at',
        'cancelled_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_context_snapshot' => 'array',
            'purpose' => AiPurpose::class,
            'audience' => AiAudience::class,
            'lane' => BusinessLane::class,
            'role_code' => UnitRoleCode::class,
            'task_profile' => AiTaskProfile::class,
            'requested_contour' => AiProcessingContour::class,
            'selected_contour' => AiProcessingContour::class,
            'model_profile_preference' => AiModelProfile::class,
            'status' => AiRunStatus::class,
            'max_cost_rub' => 'decimal:4',
            'accumulated_cost_rub' => 'decimal:4',
            'queued_at' => 'datetime',
            'prepared_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AiAgentDefinition::class, 'ai_agent_definition_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AiAgentRunStep::class);
    }

    public function policyDecisions(): HasMany
    {
        return $this->hasMany(AiPolicyDecisionRecord::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }
}
