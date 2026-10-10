<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogNode extends Model
{
    protected $fillable = [
        'level_id', 'parent_id', 'entity_type', 'entity_id', 'import_key', 'is_manual', 'name', 'slug', 'image',
        'description', 'h1', 'meta_title', 'meta_description', 'is_published', 'is_featured', 'sort_order', 'properties', 'properties_by_level',
    ];

    protected $casts = [
        'level_id' => 'integer', 'parent_id' => 'integer', 'entity_id' => 'integer',
        'is_manual' => 'boolean', 'is_published' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer',
        'properties' => 'array', 'properties_by_level' => 'array',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(CatalogLevel::class, 'level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function siteDomains(): HasMany
    {
        return $this->hasMany(CatalogSiteDomain::class, 'catalog_node_id')->orderBy('id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }
}
