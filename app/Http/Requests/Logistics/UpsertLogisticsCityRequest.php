<?php

namespace App\Http\Requests\Logistics;

use App\Enums\Logistics\CoordinateSource;
use App\Models\LogisticsCity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertLogisticsCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        $setting = LogisticsCity::query()->where('city_id', $this->route('city')?->id)->first();

        return $setting
            ? (bool) $this->user()?->can('update', $setting)
            : (bool) $this->user()?->can('create', LogisticsCity::class);
    }

    public function rules(): array
    {
        return [
            'routing_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:routing_longitude'],
            'routing_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:routing_latitude'],
            'coordinate_source' => ['nullable', Rule::enum(CoordinateSource::class)],
            'source_reference' => ['nullable', 'string', 'max:1024'],
            'is_matrix_enabled' => ['required', 'boolean'],
            'mark_verified' => ['nullable', 'boolean'],
        ];
    }
}
