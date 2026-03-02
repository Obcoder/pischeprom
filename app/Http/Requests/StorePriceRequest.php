<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // если нужно — потом повесишь policy/guard
    }

    public function rules(): array
    {
        return [
            'good_id'     => ['required', 'integer', 'exists:goods,id'],
            'price'       => ['required', 'numeric', 'min:0'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'good_id.required' => 'Не выбран товар (good_id).',
            'price.required' => 'Цена обязательна.',
            'currency_id.required' => 'Валюта обязательна.',
        ];
    }
}
