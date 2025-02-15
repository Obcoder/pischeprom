<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class product_unit extends Pivot
{
    protected $table = 'product_unit';
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
