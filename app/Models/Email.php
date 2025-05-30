<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
    ];

    public function sendings(): HasMany
    {
        return $this->hasMany(Sending::class);
    }
    public function units()
    {
        return $this->belongsToMany(Unit::class);
    }
}
