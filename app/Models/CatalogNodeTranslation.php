<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogNodeTranslation extends Model
{
    protected $fillable = ['node_id', 'locale', 'name'];

    protected $casts = ['node_id' => 'integer'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(CatalogNode::class, 'node_id');
    }
}
