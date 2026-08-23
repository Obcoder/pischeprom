<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentRunStep extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'sequence', 'step_type', 'contour', 'provider_code', 'provider_route',
        'model_id', 'sanitized_input_hash', 'safe_request_summary', 'status',
        'normalized_output_metadata', 'input_tokens', 'output_tokens', 'reasoning_tokens',
        'normalized_cost_rub', 'retry_count', 'failover_count', 'provider_request_id',
        'safe_error_code', 'safe_error_summary', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'status' => AiRunStepStatus::class,
            'normalized_output_metadata' => 'array',
            'normalized_cost_rub' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }
}
