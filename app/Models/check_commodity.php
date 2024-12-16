<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class check_commodity extends Pivot
{
    public function measure()
    {
        return $this->belongsTo(measure::class)
            ->withDefault();
    }
}
