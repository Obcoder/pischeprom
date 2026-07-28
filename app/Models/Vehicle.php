<?php

namespace App\Models;

use App\Enums\Logistics\VehicleStatus;
use App\Enums\Logistics\VehicleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Vehicle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'logistics_vehicles';

    protected $fillable = [
        'name',
        'registration_number',
        'make',
        'model',
        'year',
        'vin',
        'vehicle_type',
        'owner_entity_id',
        'status',
        'payload_capacity_kg',
        'cargo_volume_m3',
        'curb_weight_kg',
        'gross_weight_kg',
        'length_m',
        'width_m',
        'height_m',
        'axle_count',
        'max_axle_load_t',
        'fuel_type',
        'fuel_tank_capacity_l',
        'average_fuel_consumption_l_per_100km',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'year' => 'integer',
            'payload_capacity_kg' => 'decimal:3',
            'cargo_volume_m3' => 'decimal:3',
            'curb_weight_kg' => 'decimal:3',
            'gross_weight_kg' => 'decimal:3',
            'length_m' => 'decimal:3',
            'width_m' => 'decimal:3',
            'height_m' => 'decimal:3',
            'axle_count' => 'integer',
            'max_axle_load_t' => 'decimal:3',
            'fuel_tank_capacity_l' => 'decimal:3',
            'average_fuel_consumption_l_per_100km' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owner_entity_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(LogisticsTrip::class, 'vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function setRegistrationNumberAttribute(?string $value): void
    {
        $this->attributes['registration_number'] = self::normalizeRegistrationNumber($value);
    }

    public function setVinAttribute(?string $value): void
    {
        $value = mb_strtoupper(trim((string) $value));
        $this->attributes['vin'] = $value === '' ? null : preg_replace('/\s+/u', '', $value);
    }

    public static function normalizeRegistrationNumber(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value));

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    protected static function booted(): void
    {
        static::deleting(function (Vehicle $vehicle): void {
            if ($vehicle->isForceDeleting() && $vehicle->trips()->withTrashed()->exists()) {
                throw new LogicException('Нельзя физически удалить автомобиль, использованный в истории рейсов.');
            }
        });

        static::updated(function (Vehicle $vehicle): void {
            $critical = [
                'gross_weight_kg', 'length_m', 'width_m', 'height_m',
                'axle_count', 'max_axle_load_t', 'vehicle_type',
            ];

            if (! $vehicle->wasChanged($critical)) {
                return;
            }

            $vehicle->trips()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->get()
                ->each->markRouteStale();
        });
    }
}
