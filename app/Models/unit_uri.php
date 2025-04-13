<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class unit_uri extends Pivot
{
    protected $fillable = [
        'unit_id',
        'uri_id',
    ];
}
