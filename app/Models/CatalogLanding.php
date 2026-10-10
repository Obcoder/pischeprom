<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogLanding extends Model
{
    protected $fillable = [
        'catalog_node_id', 'draft_content', 'published_content', 'version',
        'published_at', 'activated_at', 'updated_by', 'published_by',
    ];

    protected $casts = [
        'catalog_node_id' => 'integer', 'draft_content' => 'array', 'published_content' => 'array',
        'version' => 'integer', 'published_at' => 'datetime', 'activated_at' => 'datetime',
        'updated_by' => 'integer', 'published_by' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(CatalogNode::class, 'catalog_node_id');
    }
}
