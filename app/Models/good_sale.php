<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class good_sale extends Pivot
{
    protected $with = [
        'measures',
    ];
    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }
}
