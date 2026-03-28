<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'entity_id' => ['required', 'exists:entities,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.good_id' => ['required', 'exists:goods,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.measure_id' => ['nullable', 'exists:measures,id'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.currency_id' => ['nullable', 'exists:currencies,id'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
