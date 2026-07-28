<?php

namespace App\Models;

use App\Enums\Logistics\CoordinateSource;
use App\Enums\Logistics\DistanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsCity extends Model
{
    use HasFactory;

    protected $table = 'logistics_cities';

    protected $fillable = [
        'city_id',
        'routing_latitude',
        'routing_longitude',
        'coordinate_source',
        'source_reference',
        'is_matrix_enabled',
        'coordinates_verified_at',
        'coordinates_verified_by',
    ];

    protected function casts(): array
    {
        return [
            'routing_latitude' => 'decimal:7',
            'routing_longitude' => 'decimal:7',
            'coordinate_source' => CoordinateSource::class,
            'is_matrix_enabled' => 'boolean',
            'coordinates_verified_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinates_verified_by');
    }

    public function hasRoutingPoint(): bool
    {
        return $this->routing_latitude !== null && $this->routing_longitude !== null;
    }

    protected static function booted(): void
    {
        static::updated(function (LogisticsCity $logisticsCity): void {
            if (! $logisticsCity->wasChanged(['routing_latitude', 'routing_longitude'])) {
                return;
            }

            LogisticsCityDistance::query()
                ->where(function ($query) use ($logisticsCity) {
                    $query->where('from_city_id', $logisticsCity->city_id)
                        ->orWhere('to_city_id', $logisticsCity->city_id);
                })
                ->where('status', '!=', DistanceStatus::Manual->value)
                ->update(['status' => DistanceStatus::Stale->value]);
        });
    }
}
