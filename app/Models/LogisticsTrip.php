<?php

namespace App\Models;

use App\Enums\Logistics\ActualDistanceSource;
use App\Enums\Logistics\RouteStatus;
use App\Enums\Logistics\TemperatureMode;
use App\Enums\Logistics\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LogisticsTrip extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'logistics_trips';

    protected $fillable = [
        'number',
        'status',
        'vehicle_id',
        'carrier_entity_id',
        'responsible_user_id',
        'planned_departure_at',
        'planned_arrival_at',
        'actual_departure_at',
        'actual_arrival_at',
        'cargo_description',
        'cargo_weight_kg',
        'cargo_volume_m3',
        'pallet_count',
        'temperature_mode',
        'temperature_min_c',
        'temperature_max_c',
        'planned_distance_m',
        'planned_duration_s',
        'actual_distance_m',
        'actual_distance_source',
        'odometer_start_km',
        'odometer_end_km',
        'routing_profile',
        'routing_profile_hash',
        'route_calculated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'temperature_mode' => TemperatureMode::class,
            'actual_distance_source' => ActualDistanceSource::class,
            'planned_departure_at' => 'datetime',
            'planned_arrival_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'cargo_weight_kg' => 'decimal:3',
            'cargo_volume_m3' => 'decimal:3',
            'pallet_count' => 'integer',
            'temperature_min_c' => 'decimal:2',
            'temperature_max_c' => 'decimal:2',
            'planned_distance_m' => 'integer',
            'planned_duration_s' => 'integer',
            'actual_distance_m' => 'integer',
            'odometer_start_km' => 'decimal:1',
            'odometer_end_km' => 'decimal:1',
            'route_calculated_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'carrier_entity_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(LogisticsTripStop::class, 'trip_id')->orderBy('sequence');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LogisticsTripExpense::class, 'trip_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(LogisticsTripRoute::class, 'trip_id')->latest('id');
    }

    public function currentRoute(): HasOne
    {
        return $this->hasOne(LogisticsTripRoute::class, 'trip_id')->where('is_current', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function markRouteStale(): void
    {
        $this->routes()
            ->where('is_current', true)
            ->where('status', RouteStatus::Calculated->value)
            ->update(['status' => RouteStatus::Stale->value]);

        $this->forceFill([
            'routing_profile_hash' => null,
            'route_calculated_at' => null,
        ])->saveQuietly();
    }

    protected static function booted(): void
    {
        static::creating(function (LogisticsTrip $trip): void {
            if (blank($trip->number)) {
                $trip->number = 'TMP-'.Str::uuid();
            }
        });

        static::created(function (LogisticsTrip $trip): void {
            if (! str_starts_with($trip->number, 'TMP-')) {
                return;
            }

            $trip->forceFill([
                'number' => sprintf('TR-%s-%06d', $trip->created_at?->format('Y') ?? now()->format('Y'), $trip->id),
            ])->saveQuietly();
        });

        static::updated(function (LogisticsTrip $trip): void {
            if ($trip->wasChanged(['vehicle_id', 'routing_profile'])) {
                $trip->markRouteStale();
            }
        });
    }
}
