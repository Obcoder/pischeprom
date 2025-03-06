<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class building_unit extends Pivot
{
    protected $fillable = [
        'building_id',
        'unit_id',
        'location_id',
    ];
}
