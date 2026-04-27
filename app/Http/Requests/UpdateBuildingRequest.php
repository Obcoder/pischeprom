<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
        ];
    }
}
