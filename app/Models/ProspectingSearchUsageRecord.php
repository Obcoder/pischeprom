<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProspectingSearchUsageRecord extends Model
{
    protected $fillable = [
        'prospecting_search_execution_id', 'provider_code', 'profile_code', 'request_count',
        'result_count', 'input_tokens', 'output_tokens', 'estimated_cost_rub',
        'safe_request_id', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost_rub' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Search usage records are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Search usage records are immutable.');
        });
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchExecution::class, 'prospecting_search_execution_id');
    }
}
