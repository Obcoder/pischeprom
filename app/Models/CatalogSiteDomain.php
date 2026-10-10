<?php

namespace App\Models;

use App\Services\Catalog\CatalogHost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogSiteDomain extends Model
{
    protected $fillable = ['catalog_node_id', 'hostname'];

    protected $casts = ['catalog_node_id' => 'integer'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(CatalogNode::class, 'catalog_node_id');
    }

    public function setHostnameAttribute(string $value): void
    {
        $this->attributes['hostname'] = CatalogHost::normalize($value);
    }
}
