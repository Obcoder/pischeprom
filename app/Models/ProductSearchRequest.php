<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSearchRequest extends Model
{
    protected $fillable = [
        'product_id',
        'engine',
        'query',
        'status',
        'results_count',
        'error_message',
        'started_at',
        'finished_at',
        'searched_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'searched_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProductSearchResult::class, 'request_id')->orderBy('position');
    }
}
