<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class city_unit extends Pivot
{
    protected $table = 'city_unit';

    protected $fillable = [
        'city_id',
        'unit_id',
    ];
}
