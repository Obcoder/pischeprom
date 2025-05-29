<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class stage_unit extends Pivot
{
    protected $table = 'stage_unit';
    protected $fillable = ['stage_id', 'unit_id', 'isActive'];

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
