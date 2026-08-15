<?php

namespace App\Models;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class UnitObservation extends Model
{
    protected $fillable = [
        'unit_id',
        'unit_business_context_id',
        'unit_source_id',
        'observation_key',
        'normalized_value',
        'summary',
        'source_reference',
        'verification_status',
        'confidence',
        'data_classification',
        'visibility_scope',
        'observed_at',
        'last_checked_at',
        'created_by_type',
        'created_by_user_id',
        'rules_version',
        'model_version',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => ObservationVerificationStatus::class,
            'data_classification' => DataClassification::class,
            'visibility_scope' => UnitVisibilityScope::class,
            'confidence' => 'integer',
            'observed_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Unit observations are retained, not deleted.');
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(UnitSource::class, 'unit_source_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
