<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodMediaFolder extends Model
{
    protected $fillable = [
        'good_id',
        'parent_id',
        'name',
        'slug',
        'path',
        'sort_order',
        'is_archive',
    ];

    protected $casts = [
        'is_archive' => 'boolean',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GoodMediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GoodMediaFolder::class, 'parent_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(GoodMedia::class, 'folder_id');
    }
}
