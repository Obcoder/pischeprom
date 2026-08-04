<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AiUsageRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:6',
            'cost_is_estimate' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('AI usage records are append-only.'));
        static::deleting(static fn (): never => throw new LogicException('AI usage records are append-only.'));
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PriceListImport::class, 'price_list_import_id');
    }
}
