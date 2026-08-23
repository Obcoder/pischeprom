<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitAlias extends Model
{
    protected $fillable = [
        'unit_id',
        'unit_business_context_id',
        'unit_source_id',
        'alias',
        'normalized_alias',
        'alias_type',
        'confidence',
        'verification_status',
        'data_classification',
        'visibility_scope',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'alias_type' => UnitAliasType::class,
            'verification_status' => ObservationVerificationStatus::class,
            'data_classification' => DataClassification::class,
            'visibility_scope' => UnitVisibilityScope::class,
            'confidence' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(UnitSource::class, 'unit_source_id');
    }

    public function businessContext(): BelongsTo
    {
        return $this->belongsTo(UnitBusinessContext::class, 'unit_business_context_id');
    }
}
