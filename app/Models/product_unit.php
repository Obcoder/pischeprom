<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class product_unit extends Pivot
{
    public function action()
    {
        return $this->belongsTo(Action::class);
    }
}
