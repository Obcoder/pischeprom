<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'product_id',
    ];

    public function unit(): HasOne
    {
        return $this->HasOne(Unit::class);
    }
}
