<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Manufacturer extends Model
{
    use HasFactory;

    protected $with = [
        'unit',
        'product',
    ];

    protected $fillable = [
        'unit_id',
        'product_id',
    ];

    public function unit(): BelongsTo
    {
        return $this->BelongsTo(Unit::class);
    }
    public function product(): BelongsTo
    {
        return $this->BelongsTo(Product::class);
    }
}
