<?php

namespace App\Http\Requests;

use App\Services\Auth\StaffAccess;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Http\FormRequest;

class VerifyGoodTradeCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(StaffAccess::class)->allows($this->user());
    }

    protected function prepareForValidation(): void
    {
        $codes = $this->input('codes');
        if (is_array($codes)) {
            // Preserve unknown keys so validation rejects them rather than silently dropping them.
            $this->merge(['codes' => array_replace($codes, GoodTradeCodes::normalize($codes))]);
        }
    }

    public function rules(): array
    {
        $rules = ['codes' => ['required', 'array:'.implode(',', GoodTradeCodes::FIELDS), 'min:1', 'max:11']];
        foreach (GoodTradeCodes::rules() as $field => $fieldRules) {
            $rules['codes.'.$field] = ['sometimes', 'required', 'max:32', ...array_diff($fieldRules, ['nullable'])];
        }

        return $rules;
    }
}
