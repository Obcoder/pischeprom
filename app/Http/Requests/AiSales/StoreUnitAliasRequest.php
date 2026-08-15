<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['nullable', 'integer', 'exists:unit_business_contexts,id'],
            'unit_source_id' => ['nullable', 'integer', 'exists:unit_sources,id'],
            'alias' => ['required', 'string', 'max:512'],
            'alias_type' => ['required', Rule::enum(UnitAliasType::class)],
            'confidence' => ['nullable', 'integer', 'between:0,100'],
            'data_classification' => ['required', Rule::enum(DataClassification::class)],
            'visibility_scope' => ['required', Rule::enum(UnitVisibilityScope::class)],
        ];
    }
}
