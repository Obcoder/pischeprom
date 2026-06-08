<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPipelineCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_pipeline_id',
        'supplier_pipeline_stage_id',
        'unit_id',
        'title',
        'notes',
        'next_contact_at',
        'sort_order',
    ];

    protected $casts = [
        'next_contact_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(SupplierPipeline::class, 'supplier_pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(SupplierPipelineStage::class, 'supplier_pipeline_stage_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
