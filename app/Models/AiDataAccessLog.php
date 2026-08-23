<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiProcessingDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AiDataAccessLog extends Model
{
    protected $fillable = [
        'ai_agent_run_id', 'ai_tool_call_id', 'dto_type', 'source_type', 'source_id',
        'contour', 'classification_summary', 'row_count', 'byte_count', 'decision', 'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'classification_summary' => 'array',
            'decision' => AiProcessingDecision::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('AI data access logs are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('AI data access logs are append-only.');
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }
}
