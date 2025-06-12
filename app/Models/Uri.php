<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uri extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'is_valid',
        'follow',
        'has_brilliant_foremost_design',
    ];

    public function owners()
    {
        return $this->belongsToMany(Unit::class);
    }
}
