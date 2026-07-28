<?php

namespace App\Models;

use App\Enums\Logistics\StopOperationType;
use App\Enums\Logistics\StopType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsTripStop extends Model
{
    use HasFactory;

    protected $table = 'logistics_trip_stops';

    protected $fillable = [
        'trip_id',
        'sequence',
        'city_id',
        'stop_type',
        'operation_type',
        'address',
        'latitude',
        'longitude',
        'planned_arrival_at',
        'planned_departure_at',
        'actual_arrival_at',
        'actual_departure_at',
        'cargo_weight_change_kg',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'stop_type' => StopType::class,
            'operation_type' => StopOperationType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'planned_arrival_at' => 'datetime',
            'planned_departure_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'cargo_weight_change_kg' => 'decimal:3',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(LogisticsTrip::class, 'trip_id')->withTrashed();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    protected static function booted(): void
    {
        static::saved(fn (LogisticsTripStop $stop) => $stop->trip?->markRouteStale());
        static::deleted(fn (LogisticsTripStop $stop) => $stop->trip?->markRouteStale());
    }
}
