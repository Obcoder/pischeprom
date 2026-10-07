<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogField extends Model
{
    protected $fillable = ['level_id', 'key', 'label', 'type', 'required', 'is_public', 'options', 'sort_order'];

    protected $casts = ['required' => 'boolean', 'is_public' => 'boolean', 'options' => 'array', 'sort_order' => 'integer'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(CatalogLevel::class, 'level_id');
    }
}
