<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Field extends Model
{
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
}
