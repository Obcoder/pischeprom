<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogLevel extends Model
{
    protected $fillable = ['name', 'entity_type', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function fields(): HasMany
    {
        return $this->hasMany(CatalogField::class, 'level_id')->orderBy('sort_order')->orderBy('id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(CatalogNode::class, 'level_id');
    }
}
