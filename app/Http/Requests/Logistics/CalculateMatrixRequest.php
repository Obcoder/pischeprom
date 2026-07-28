<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsCityDistance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        return (bool) $this->user()?->can('create', LogisticsCityDistance::class);
    }

    public function rules(): array
    {
        $max = max(2, (int) config('logistics.matrix_max_cities_per_request', 50));
        $fullMatrix = $this->boolean('full_matrix');

        return [
            'full_matrix' => ['nullable', 'boolean'],
            'city_ids' => [
                Rule::requiredIf(! $fullMatrix),
                Rule::prohibitedIf($fullMatrix),
                'array',
                'min:2',
                'max:'.$max,
            ],
            'city_ids.*' => ['required', 'integer', 'distinct', 'exists:logistics_cities,city_id'],
            'routing_profile' => ['required', 'string', 'in:truck,auto'],
            'refresh' => ['nullable', 'boolean'],
            'missing_only' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_matrix' => $this->boolean('full_matrix'),
            'routing_profile' => $this->input('routing_profile', config('logistics.default_routing_profile', 'truck')),
            'missing_only' => $this->boolean('missing_only', true),
            'refresh' => $this->boolean('refresh'),
        ]);
    }
}
