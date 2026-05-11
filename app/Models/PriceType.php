<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'currency_id',
        'markup_percent',
        'target_margin_percent',
        'rounding_step',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'markup_percent' => 'float',
        'target_margin_percent' => 'float',
        'rounding_step' => 'float',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(GoodPriceTypeValue::class);
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(GoodPriceFormula::class);
    }
}
