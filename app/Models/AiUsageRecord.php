<?php

namespace App\Models;

use App\Domain\AiSales\Enums\AiProcessingContour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AiUsageRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'contour' => AiProcessingContour::class,
            'estimated_cost' => 'decimal:6',
            'cost_is_estimate' => 'boolean',
            'normalized_rub_amount' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('AI usage records are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('AI usage records are append-only.');
        });
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PriceListImport::class, 'price_list_import_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiAgentRun::class, 'ai_agent_run_id');
    }

    public function runStep(): BelongsTo
    {
        return $this->belongsTo(AiAgentRunStep::class, 'ai_agent_run_step_id');
    }
}
