<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisRoutePoint extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'route_draft_id',
        'sequence_no',
        'entity_id',
        'title',
        'address_text',
        'lat',
        'lon',
        'point_type',
    ];

    protected $casts = [
        'route_draft_id' => 'integer',
        'sequence_no' => 'integer',
        'entity_id' => 'integer',
        'lat' => 'float',
        'lon' => 'float',
        'created_at' => 'datetime',
    ];

    public function routeDraft(): BelongsTo
    {
        return $this->belongsTo(GisRouteDraft::class, 'route_draft_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withDefault();
    }
}
