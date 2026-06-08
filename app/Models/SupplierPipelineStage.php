<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPipelineStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_pipeline_id',
        'name',
        'color',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SupplierPipeline::class, 'supplier_pipeline_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(SupplierPipelineCard::class, 'supplier_pipeline_stage_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
