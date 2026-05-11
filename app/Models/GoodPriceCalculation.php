<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodPriceCalculation extends Model
{
    protected $fillable = [
        'good_id',
        'purchase_id',
        'quotation_id',
        'price_type_id',
        'formula_id',
        'currency_id',
        'created_by',
        'name',
        'comment',
        'input',
        'result',
        'purchase_net_per_kg',
        'sale_net_per_kg',
        'sale_gross_per_kg',
        'sale_net_per_box',
        'sale_gross_per_box',
        'profit_per_kg',
        'margin_percent',
        'markup_percent',
    ];

    protected $casts = [
        'input' => 'array',
        'result' => 'array',
        'purchase_net_per_kg' => 'float',
        'sale_net_per_kg' => 'float',
        'sale_gross_per_kg' => 'float',
        'sale_net_per_box' => 'float',
        'sale_gross_per_box' => 'float',
        'profit_per_kg' => 'float',
        'margin_percent' => 'float',
        'markup_percent' => 'float',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(GoodPriceFormula::class, 'formula_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
