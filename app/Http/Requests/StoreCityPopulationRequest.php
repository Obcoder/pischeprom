<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityPopulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $city = $this->route('city');
        $nextYear = now()->year + 1;

        return [
            'year' => [
                'required',
                'integer',
                "between:1,{$nextYear}",
                Rule::unique('city_populations', 'year')
                    ->where('city_id', $city->id),
            ],

            'population' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
