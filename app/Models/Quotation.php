<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $casts = [
        'price' => 'decimal:4',
        'denominator' => 'float',
        'quoted_at' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'price_includes_vat' => 'boolean',
        'vat_rate' => 'decimal:4',
        'minimum_quantity' => 'decimal:4',
        'lead_time_days' => 'integer',
    ];

    protected $fillable = [
        'good_id',
        'unit_id',
        'price',
        'currency_id',
        'measure_id',
        'denominator',
        'quoted_at',
        'valid_until',
        'price_includes_vat',
        'vat_rate',
        'minimum_quantity',
        'lead_time_days',
        'notes',
    ];

    protected $appends = ['missing_fields', 'is_complete', 'is_current'];

    protected $with = [
        'good',
        'unit',
        'currency',
        'measure',
    ];

    /** Unknown historical currency and units must be corrected, never inferred. */
    public function getMissingFieldsAttribute(): array
    {
        $missing = [];
        if ($this->price === null || ! is_numeric($this->price) || (float) $this->price < 0) {
            $missing[] = 'price';
        }
        foreach (['currency_id', 'measure_id'] as $field) {
            if (! $this->{$field}) {
                $missing[] = $field;
            }
        }
        if (! $this->denominator || $this->denominator <= 0) {
            $missing[] = 'denominator';
        }

        return $missing;
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->missing_fields === [];
    }

    public function getIsCurrentAttribute(): bool
    {
        $today = today();

        return (! $this->quoted_at || $this->quoted_at->lte($today))
            && (! $this->valid_until || $this->valid_until->gte($today));
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->where(fn (Builder $query) => $query->whereNull('quoted_at')->orWhereDate('quoted_at', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
