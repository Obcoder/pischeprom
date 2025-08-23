<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Uri extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'is_valid',
        'follow',
        'has_brilliant_foremost_design',
    ];
    protected $with = [
        'owners',
    ];

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
}
