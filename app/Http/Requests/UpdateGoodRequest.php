<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateGoodRequest extends StoreGoodRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('goods', 'slug')->ignore($this->route('good'))],
        ];
    }
}
