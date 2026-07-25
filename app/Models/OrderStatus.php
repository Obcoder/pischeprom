<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStatus extends Model
{
    public const OPEN = 'open';

    public const DEFERRED = 'deferred';

    public const CLOSED = 'closed';

    protected $fillable = [
        'code',
        'name',
        'color',
        'sort_order',
        'is_closed',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
