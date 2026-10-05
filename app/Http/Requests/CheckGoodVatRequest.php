<?php

namespace App\Http\Requests;

use App\Services\Auth\StaffAccess;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckGoodVatRequest extends FormRequest
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
            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
            'operation' => ['sometimes', Rule::in(['domestic', 'import', 'export'])],
            'check_date' => ['sometimes', 'date_format:Y-m-d'],
            ...GoodTradeCodes::rules(),
        ];
    }
}
