<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reusable staff template. Assigned prices retain independent JSON snapshots. */
class GoodPricingElement extends Model
{
    protected $attributes = ['revision' => 1];

    protected $fillable = [
        'name', 'kind', 'value', 'percentage_base', 'includes_vat', 'vat_rate',
        'currency_id', 'measurement',
    ];

    protected $casts = [
        'value' => 'float', 'vat_rate' => 'float', 'includes_vat' => 'boolean',
        'measurement' => 'array', 'revision' => 'integer',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
