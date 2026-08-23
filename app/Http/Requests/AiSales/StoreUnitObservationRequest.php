<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitObservationRequest extends FormRequest
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
            'observation_key' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9_.-]+$/'],
            'normalized_value' => ['nullable', 'string', 'max:1024'],
            'summary' => ['required', 'string', 'max:4000'],
            'source_reference' => ['nullable', 'string', 'max:1024'],
            'confidence' => ['nullable', 'integer', 'between:0,100'],
            'data_classification' => ['required', Rule::enum(DataClassification::class)],
            'visibility_scope' => ['required', Rule::enum(UnitVisibilityScope::class)],
            'observed_at' => ['nullable', 'date'],
            'last_checked_at' => ['nullable', 'date'],
            'rules_version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
