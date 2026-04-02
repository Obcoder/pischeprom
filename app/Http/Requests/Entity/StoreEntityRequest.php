<?php

namespace App\Http\Requests\Entity;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'entity_classification_id' => ['nullable', 'exists:entity_classifications,id'],
            'INN' => ['nullable', 'string', 'max:32'],
            'OGRN' => ['nullable', 'string', 'max:32'],
            'country_id' => ['nullable', 'exists:countries,id'],

            'buildings' => ['array'],
            'buildings.*' => ['integer', 'exists:buildings,id'],

            'cities' => ['array'],
            'cities.*' => ['integer', 'exists:cities,id'],

            'emails' => ['array'],
            'emails.*' => ['integer', 'exists:emails,id'],

            'telephones' => ['array'],
            'telephones.*' => ['integer', 'exists:telephones,id'],

            'units' => ['array'],
            'units.*' => ['integer', 'exists:units,id'],

            'chats' => ['array'],
            'chats.*' => ['integer', 'exists:chats,id'],
        ];
    }
}
