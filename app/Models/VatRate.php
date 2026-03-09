<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatRate extends Model
{
    protected $fillable = [
        'title',
        'rate',
    ];

    public function goods(): HasMany
    {
        return $this->hasMany(Good::class);
    }
}
