<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['nullable', 'integer', 'exists:unit_business_contexts,id'],
            'source_type' => ['required', 'string', 'max:32'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:1024'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'data_classification' => ['required', Rule::enum(DataClassification::class)],
            'visibility_scope' => ['required', Rule::enum(UnitVisibilityScope::class)],
            'observed_at' => ['nullable', 'date'],
            'last_checked_at' => ['nullable', 'date'],
        ];
    }
}
