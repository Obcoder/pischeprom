<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodPriceTypeValue extends Model
{
    use \App\Models\Concerns\TracksGoodPriceHistory;

    protected $attributes = ['revision' => 1];

    protected $fillable = [
        'good_id',
        'price_type_id',
        'calculation_id',
        'currency_id',
        'price_net',
        'price_gross',
        'vat_rate',
        'is_manual',
        'manual_comment',
        'is_published',
        'valid_from',
        'valid_to',
        'pricing_input',
        'pricing_result',
    ];

    protected $casts = [
        'revision' => 'integer',
        'price_net' => 'float',
        'price_gross' => 'float',
        'vat_rate' => 'float',
        'is_manual' => 'boolean',
        'is_published' => 'boolean',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'pricing_input' => 'array',
        'pricing_result' => 'array',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(GoodPriceCalculation::class, 'calculation_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
