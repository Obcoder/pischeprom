<?php

namespace App\Http\Requests\AiPriceLists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignPriceListSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('assignSupplier', $this->route('priceListImport'));
    }

    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'bind_source' => ['sometimes', 'boolean'],
        ];
    }
}
