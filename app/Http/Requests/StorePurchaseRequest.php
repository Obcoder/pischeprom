<?php

namespace App\Http\Requests\Purchase;

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
            'goods' => ['nullable', 'array'],
            'goods.*.id' => ['required', 'exists:goods,id'],
        ];
    }
}
