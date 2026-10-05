<?php

namespace App\Http\Requests;

use App\Services\Auth\StaffAccess;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendGoodTradeCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(StaffAccess::class)->allows($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(GoodTradeCodes::normalize($this->all()));
    }

    public function rules(): array
    {
        if ($this->isMethod('get')) {
            return [];
        }

        return [
            'good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'product_ids' => ['sometimes', 'array', 'max:20'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'requested_fields' => ['sometimes', 'array', 'list', 'min:1', 'max:11'],
            'requested_fields.*' => ['required', 'string', 'distinct:strict', Rule::in(GoodTradeCodes::FIELDS)],
            ...GoodTradeCodes::rules(),
        ];
    }
}
