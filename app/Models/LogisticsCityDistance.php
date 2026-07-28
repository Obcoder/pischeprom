<?php

namespace App\Models;

use App\Enums\Logistics\DistanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class LogisticsCityDistance extends Model
{
    use HasFactory;

    protected $table = 'logistics_city_distances';

    protected $fillable = [
        'from_city_id',
        'to_city_id',
        'routing_profile',
        'vehicle_profile_hash',
        'status',
        'distance_m',
        'duration_s',
        'from_latitude_snapshot',
        'from_longitude_snapshot',
        'to_latitude_snapshot',
        'to_longitude_snapshot',
        'provider',
        'routing_engine_version',
        'osm_data_version',
        'request_hash',
        'calculated_at',
        'expires_at',
        'manual_note',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => DistanceStatus::class,
            'distance_m' => 'integer',
            'duration_s' => 'integer',
            'from_latitude_snapshot' => 'decimal:7',
            'from_longitude_snapshot' => 'decimal:7',
            'to_latitude_snapshot' => 'decimal:7',
            'to_longitude_snapshot' => 'decimal:7',
            'calculated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    protected static function booted(): void
    {
        static::saving(function (LogisticsCityDistance $distance): void {
            $distance->vehicle_profile_hash = $distance->vehicle_profile_hash ?: 'default';

            if ((int) $distance->from_city_id === (int) $distance->to_city_id) {
                throw new InvalidArgumentException('Города начала и конца матричной пары должны различаться.');
            }
        });
    }
}
