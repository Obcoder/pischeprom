<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Industry extends Model
{
    protected $fillable = [
        'code',
        'title',
    ];

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
