<?php

namespace App\Models;

use App\Enums\Logistics\RouteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsTripRoute extends Model
{
    use HasFactory;

    protected $table = 'logistics_trip_routes';

    protected $fillable = [
        'trip_id',
        'is_current',
        'status',
        'routing_profile',
        'vehicle_profile_hash',
        'request_hash',
        'distance_m',
        'duration_s',
        'shape_polyline6',
        'legs',
        'routing_options',
        'provider',
        'routing_engine_version',
        'osm_data_version',
        'calculated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'status' => RouteStatus::class,
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'legs' => 'array',
            'routing_options' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(LogisticsTrip::class, 'trip_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
