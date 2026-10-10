<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'good_id',
        'measure_id',
        'good_name',
        'good_slug',
        'image_url',
        'quantity',
        'denominator',
        'line_weight',
        'price_gross',
        'currency_code',
        'line_total',
        'country_name',
        'snapshot',
    ];

    protected $casts = [
        'quantity' => 'float',
        'denominator' => 'float',
        'line_weight' => 'float',
        'price_gross' => 'float',
        'line_total' => 'float',
        'snapshot' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    /** Historical order units belong to the line, not today's product card. */
    public function measurement(): array
    {
        return $this->snapshot['measurement'] ?? [
            'measure_id' => $this->measure_id ? (int) $this->measure_id : null,
            'unit_label' => $this->measure?->name,
            'kilograms_per_unit' => $this->denominator,
        ];
    }

    public function getUnitPriceAttribute(): ?float
    {
        return $this->price_gross;
    }

    public function getTotalAmountAttribute(): ?float
    {
        return $this->line_total;
    }
}
