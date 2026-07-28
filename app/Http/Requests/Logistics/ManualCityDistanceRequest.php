<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsCityDistance;
use Illuminate\Foundation\Http\FormRequest;

class ManualCityDistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', LogisticsCityDistance::class);
    }

    public function rules(): array
    {
        return [
            'from_city_id' => ['required', 'integer', 'different:to_city_id', 'exists:logistics_cities,city_id'],
            'to_city_id' => ['required', 'integer', 'different:from_city_id', 'exists:logistics_cities,city_id'],
            'routing_profile' => ['required', 'string', 'in:truck,auto'],
            'distance_m' => ['required', 'integer', 'gt:0'],
            'duration_s' => ['nullable', 'integer', 'gt:0'],
            'manual_note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'routing_profile' => $this->input('routing_profile', config('logistics.default_routing_profile', 'truck')),
        ]);
    }
}
