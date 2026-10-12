<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodPriceHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Price history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Price history is append-only.'));
    }
}
