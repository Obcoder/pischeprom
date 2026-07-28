<?php

namespace App\Http\Requests\Logistics;

use App\Enums\Logistics\VehicleStatus;
use App\Enums\Logistics\VehicleType;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        return (bool) $this->user()?->can('create', Vehicle::class);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => [
                'required', 'string', 'max:32',
                Rule::unique('logistics_vehicles', 'registration_number')->ignore($vehicle?->id),
            ],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'vin' => ['nullable', 'string', 'max:32', Rule::unique('logistics_vehicles', 'vin')->ignore($vehicle?->id)],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'owner_entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'payload_capacity_kg' => ['nullable', 'numeric', 'gt:0'],
            'cargo_volume_m3' => ['nullable', 'numeric', 'gt:0'],
            'curb_weight_kg' => ['nullable', 'numeric', 'gt:0'],
            'gross_weight_kg' => ['nullable', 'numeric', 'gt:0', 'gte:curb_weight_kg'],
            'length_m' => ['nullable', 'numeric', 'gt:0'],
            'width_m' => ['nullable', 'numeric', 'gt:0'],
            'height_m' => ['nullable', 'numeric', 'gt:0'],
            'axle_count' => ['nullable', 'integer', 'between:1,16'],
            'max_axle_load_t' => ['nullable', 'numeric', 'gt:0'],
            'fuel_type' => ['nullable', 'string', 'max:32'],
            'fuel_tank_capacity_l' => ['nullable', 'numeric', 'gt:0'],
            'average_fuel_consumption_l_per_100km' => ['nullable', 'numeric', 'gt:0'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'registration_number' => Vehicle::normalizeRegistrationNumber($this->input('registration_number')),
            'vin' => filled($this->input('vin'))
                ? preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $this->input('vin'))))
                : null,
            'vehicle_type' => $this->input('vehicle_type', VehicleType::Truck->value),
            'status' => $this->input('status', VehicleStatus::Active->value),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function messages(): array
    {
        return [
            'registration_number.unique' => 'Автомобиль с таким нормализованным госномером уже существует.',
            'gross_weight_kg.gte' => 'Полная масса не может быть меньше снаряжённой массы.',
        ];
    }
}
