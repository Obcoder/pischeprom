<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPipeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(SupplierPipelineStage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(SupplierPipelineCard::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
