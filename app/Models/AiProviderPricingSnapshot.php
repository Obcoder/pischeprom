<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AiProviderPricingSnapshot extends Model
{
    protected $fillable = [
        'provider_code', 'provider_route', 'model_id', 'version', 'currency',
        'input_per_million', 'output_per_million', 'reasoning_per_million',
        'effective_at', 'expires_at', 'source_reference', 'source_hash',
        'recorded_by_reference',
    ];

    protected function casts(): array
    {
        return [
            'input_per_million' => 'decimal:6',
            'output_per_million' => 'decimal:6',
            'reasoning_per_million' => 'decimal:6',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('AI provider pricing snapshots are immutable; record a new version.');
        });
        static::deleting(static function (): never {
            throw new LogicException('AI provider pricing snapshots are immutable.');
        });
    }
}
