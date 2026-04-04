<?php

namespace App\Http\Requests\Telephone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTelephoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $telephoneId = $this->route('telephone')?->id ?? $this->route('telephone');

        return [
            'number' => [
                'required',
                'string',
                'max:16',
                Rule::unique('telephones', 'number')->ignore($telephoneId),
            ],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer', 'exists:entities,id'],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'exists:units,id'],
        ];
    }
}
