<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class entity_unit extends Pivot
{
    protected $fillable = [
        'entity_id',
        'unit_id',
    ];
}
