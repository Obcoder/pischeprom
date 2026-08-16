<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'ai_agent_run_step_id', 'call_id', 'tool_code', 'tool_version',
        'workflow_code', 'workflow_version', 'workflow_hash', 'tool_schema_hash',
        'tool_policy_version', 'contour', 'unit_id', 'unit_business_context_id', 'actor_user_id',
        'ai_policy_decision_id', 'context_snapshot', 'arguments_hash', 'safe_input_hash',
        'output_hash', 'redacted_arguments_summary', 'redacted_output_summary',
        'authorization_decision', 'policy_decision_hash', 'idempotency_key', 'side_effect_class',
        'row_count', 'byte_count', 'query_count', 'redaction_count', 'budget_reservation',
        'duration_ms', 'status', 'error_category', 'safe_error_code', 'safe_error_summary',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'context_snapshot' => 'array',
            'budget_reservation' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AiAgentRunStep::class, 'ai_agent_run_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function policyDecision(): BelongsTo
    {
        return $this->belongsTo(AiPolicyDecisionRecord::class, 'ai_policy_decision_id');
    }
}
