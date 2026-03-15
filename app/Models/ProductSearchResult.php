<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSearchResult extends Model
{
    protected $fillable = [
        'request_id',
        'position',
        'title',
        'url',
        'domain',
        'snippet',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductSearchRequest::class, 'request_id');
    }
}
