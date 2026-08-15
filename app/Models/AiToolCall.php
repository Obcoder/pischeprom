<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'ai_agent_run_step_id', 'call_id', 'tool_code', 'tool_version',
        'contour', 'unit_id', 'unit_business_context_id', 'context_snapshot', 'arguments_hash',
        'output_hash', 'redacted_arguments_summary', 'redacted_output_summary',
        'authorization_decision', 'policy_decision_hash', 'idempotency_key', 'side_effect_class',
        'duration_ms', 'status', 'safe_error_code', 'safe_error_summary',
    ];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'context_snapshot' => 'array',
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
}
