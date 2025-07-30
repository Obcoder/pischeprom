<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class check_commodity extends Pivot
{
    public function measure(): BelongsTo
    {
        return $this->belongsTo(measure::class)
            ->withDefault();
    }
}
