<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class UnitSource extends Model
{
    protected $fillable = [
        'unit_id',
        'unit_business_context_id',
        'source_key',
        'source_type',
        'source_label',
        'source_reference',
        'source_url',
        'data_classification',
        'visibility_scope',
        'observed_at',
        'last_checked_at',
        'created_by_type',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_classification' => DataClassification::class,
            'visibility_scope' => UnitVisibilityScope::class,
            'observed_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Unit provenance sources are retained, not deleted.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(UnitObservation::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(UnitAlias::class);
    }
}
