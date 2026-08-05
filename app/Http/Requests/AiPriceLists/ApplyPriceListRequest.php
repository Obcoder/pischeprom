<?php

namespace App\Http\Requests\AiPriceLists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApplyPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('apply', $this->route('priceListImport'));
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
