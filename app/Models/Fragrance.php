<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fragrance extends Model
{
    protected $fillable = [
        'brand_id',
        'name',
    ];
    protected $with = [
        'brand',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
