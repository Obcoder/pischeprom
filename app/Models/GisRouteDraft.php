<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GisRouteDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'transport_mode',
        'distance_m',
        'duration_sec',
        'route_geometry_json',
        'provider_response_summary',
        'created_by',
    ];

    protected $casts = [
        'distance_m' => 'integer',
        'duration_sec' => 'integer',
        'route_geometry_json' => 'array',
        'provider_response_summary' => 'array',
        'created_by' => 'integer',
    ];

    public function points(): HasMany
    {
        return $this->hasMany(GisRoutePoint::class, 'route_draft_id')
            ->orderBy('sequence_no');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }
}
