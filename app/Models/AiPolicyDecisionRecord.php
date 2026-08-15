<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AiPolicyDecisionRecord extends Model
{
    protected $table = 'ai_policy_decisions';

    protected $fillable = [
        'ai_agent_run_id', 'ai_agent_run_step_id', 'ai_tool_call_id', 'disclosure_policy_version',
        'contour_policy_version', 'classification_snapshot', 'visibility_snapshot', 'decision',
        'contour', 'reason_code', 'redaction_count', 'requires_human_review', 'decision_hash',
    ];

    protected function casts(): array
    {
        return [
            'classification_snapshot' => 'array',
            'visibility_snapshot' => 'array',
            'decision' => AiProcessingDecision::class,
            'contour' => AiProcessingContour::class,
            'requires_human_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('AI policy decisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('AI policy decisions are retained.');
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }
}
