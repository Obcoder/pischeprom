<?php

namespace App\Models;

use App\Models\Concerns\TracksGoodPriceHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Only the staff pricing workspace and the authorized customer cabinet read this table. */
class GoodEntityPrice extends Model
{
    use TracksGoodPriceHistory;

    protected $attributes = ['revision' => 1];

    protected $guarded = ['id', 'revision'];

    protected $casts = [
        'revision' => 'integer',
        'price_net' => 'float', 'price_gross' => 'float', 'vat_rate' => 'float',
        'is_active' => 'boolean', 'valid_from' => 'date:Y-m-d', 'valid_to' => 'date:Y-m-d',
        'pricing_input' => 'array', 'pricing_result' => 'array',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
