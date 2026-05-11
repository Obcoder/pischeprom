<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodPriceFormula extends Model
{
    protected $fillable = [
        'good_id',
        'price_type_id',
        'name',
        'code',
        'formula',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'formula' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function priceType(): BelongsTo
    {
        return $this->belongsTo(PriceType::class);
    }
}
