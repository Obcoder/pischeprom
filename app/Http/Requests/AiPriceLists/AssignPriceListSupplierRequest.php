<?php

namespace App\Http\Requests\AiPriceLists;

use Illuminate\Foundation\Http\FormRequest;

class AssignPriceListSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignSupplier', $this->route('priceListImport')) ?? false;
    }

    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'bind_source' => ['sometimes', 'boolean'],
        ];
    }
}
