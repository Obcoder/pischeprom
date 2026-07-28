<?php

namespace App\Http\Requests\Logistics;

use App\Enums\Logistics\ActualDistanceSource;
use App\Enums\Logistics\StopOperationType;
use App\Enums\Logistics\TemperatureMode;
use App\Enums\Logistics\TripStatus;
use App\Enums\Logistics\VehicleStatus;
use App\Models\LogisticsTrip;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLogisticsTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', LogisticsTrip::class);
    }

    public function rules(): array
    {
        $trip = $this->route('trip');
        $stopsRule = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'number' => ['nullable', 'string', 'max:64', Rule::unique('logistics_trips', 'number')->ignore($trip?->id)],
            'status' => ['required', Rule::enum(TripStatus::class)],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('logistics_vehicles', 'id')->whereNull('deleted_at'),
            ],
            'carrier_entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'planned_departure_at' => ['nullable', 'date'],
            'planned_arrival_at' => ['nullable', 'date', 'after_or_equal:planned_departure_at'],
            'actual_departure_at' => ['nullable', 'date'],
            'actual_arrival_at' => ['nullable', 'date', 'after_or_equal:actual_departure_at'],
            'cargo_description' => ['nullable', 'string', 'max:10000'],
            'cargo_weight_kg' => ['nullable', 'numeric', 'gte:0'],
            'cargo_volume_m3' => ['nullable', 'numeric', 'gte:0'],
            'pallet_count' => ['nullable', 'integer', 'gte:0'],
            'temperature_mode' => ['nullable', Rule::enum(TemperatureMode::class)],
            'temperature_min_c' => ['nullable', 'numeric', 'between:-100,100'],
            'temperature_max_c' => ['nullable', 'numeric', 'between:-100,100', 'gte:temperature_min_c'],
            'actual_distance_m' => ['nullable', 'integer', 'gte:0'],
            'actual_distance_source' => ['nullable', Rule::enum(ActualDistanceSource::class)],
            'odometer_start_km' => ['nullable', 'numeric', 'gte:0'],
            'odometer_end_km' => ['nullable', 'numeric', 'gte:odometer_start_km'],
            'routing_profile' => ['required', 'string', 'in:truck,auto'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'acknowledge_vehicle_warning' => ['nullable', 'boolean'],

            'stops' => [$stopsRule, 'array', 'min:2', 'max:50'],
            'stops.*.city_id' => ['required', 'integer', 'exists:cities,id'],
            'stops.*.operation_type' => ['nullable', Rule::enum(StopOperationType::class)],
            'stops.*.address' => ['nullable', 'string', 'max:1024'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:stops.*.longitude'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:stops.*.latitude'],
            'stops.*.planned_arrival_at' => ['nullable', 'date'],
            'stops.*.planned_departure_at' => ['nullable', 'date'],
            'stops.*.actual_arrival_at' => ['nullable', 'date'],
            'stops.*.actual_departure_at' => ['nullable', 'date'],
            'stops.*.cargo_weight_change_kg' => ['nullable', 'numeric'],
            'stops.*.notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('vehicle_id')) {
                return;
            }

            $vehicle = Vehicle::query()->find($this->integer('vehicle_id'));

            if (! $vehicle) {
                return;
            }

            $cargoWeight = $this->input('cargo_weight_kg');
            if ($cargoWeight !== null && $vehicle->payload_capacity_kg !== null
                && (float) $cargoWeight > (float) $vehicle->payload_capacity_kg) {
                $validator->errors()->add(
                    'cargo_weight_kg',
                    'Вес груза превышает грузоподъёмность выбранного автомобиля ('.
                    number_format((float) $vehicle->payload_capacity_kg, 0, ',', ' ').' кг).'
                );
            }

            $requiresAvailableVehicle = in_array($this->input('status'), [
                TripStatus::Planned->value,
                TripStatus::InProgress->value,
            ], true);

            $unavailable = ! $vehicle->is_active || $vehicle->status !== VehicleStatus::Active;
            if ($requiresAvailableVehicle && $unavailable && ! $this->boolean('acknowledge_vehicle_warning')) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Автомобиль неактивен или находится в ремонте. Подтвердите предупреждение для явного назначения.'
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', TripStatus::Draft->value),
            'routing_profile' => $this->input('routing_profile', config('logistics.default_routing_profile', 'truck')),
        ]);
    }

    public function messages(): array
    {
        return [
            'planned_arrival_at.after_or_equal' => 'Плановое прибытие не может быть раньше отправления.',
            'actual_arrival_at.after_or_equal' => 'Фактическое прибытие не может быть раньше отправления.',
            'odometer_end_km.gte' => 'Конечное значение одометра не может быть меньше начального.',
            'temperature_max_c.gte' => 'Максимальная температура не может быть ниже минимальной.',
            'stops.min' => 'Для рейса нужны минимум две остановки.',
        ];
    }
}
