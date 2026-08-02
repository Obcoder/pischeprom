<?php

namespace App\Http\Requests\Logistics;

use App\Enums\Logistics\TripStatus;
use App\Models\LogisticsTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MapFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        return (bool) $this->user()?->can('viewAny', LogisticsTrip::class);
    }

    public function rules(): array
    {
        $featureLimit = max(1, (int) config('logistics.map.max_features', 1000));
        $tripLimit = max(1, (int) config('logistics.map.max_selected_trips', 20));

        return [
            'bbox' => ['required', 'string', 'max:128'],
            'zoom' => ['required', 'numeric', 'between:0,24'],
            'layers' => ['nullable', 'array', 'max:3'],
            'layers.*' => ['string', 'distinct', Rule::in(['cities', 'trips', 'entities'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$featureLimit],
            'trip_ids' => ['nullable', 'array', 'max:'.$tripLimit],
            'trip_ids.*' => ['integer', 'distinct', 'exists:logistics_trips,id'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'distinct', Rule::enum(TripStatus::class)],
            'vehicle_id' => ['nullable', 'integer', 'exists:logistics_vehicles,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('bbox')) {
                return;
            }

            $parts = array_map('trim', explode(',', (string) $this->input('bbox')));
            if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
                $validator->errors()->add('bbox', 'bbox должен содержать west,south,east,north.');

                return;
            }

            [$west, $south, $east, $north] = array_map('floatval', $parts);
            if ($west < -180 || $west > 180 || $east < -180 || $east > 180
                || $south < -90 || $south > 90 || $north < -90 || $north > 90
                || $south >= $north || $west === $east) {
                $validator->errors()->add('bbox', 'bbox находится вне допустимого диапазона или имеет нулевую площадь.');
            }
        }];
    }

    /** @return array{west: float, south: float, east: float, north: float} */
    public function bounds(): array
    {
        [$west, $south, $east, $north] = array_map(
            'floatval',
            explode(',', (string) $this->validated('bbox'))
        );

        return compact('west', 'south', 'east', 'north');
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        $layers = $this->input('layers');
        $tripIds = $this->input('trip_ids');

        $this->merge([
            'layers' => $layers === null ? ['cities', 'trips'] : (array) $layers,
            'status' => $status === null || $status === '' ? null : (array) $status,
            'trip_ids' => $tripIds === null || $tripIds === '' ? null : (array) $tripIds,
            'zoom' => $this->input('zoom', config('logistics.map.default_zoom', 2.3)),
        ]);
    }
}
