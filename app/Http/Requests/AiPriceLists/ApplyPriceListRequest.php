<?php

namespace App\Http\Requests\AiPriceLists;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('apply', $this->route('priceListImport')) ?? false;
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
            'item_ids' => ['sometimes', 'array', 'max:20000'],
            'item_ids.*' => ['integer', 'distinct'],
        ];
    }
}
