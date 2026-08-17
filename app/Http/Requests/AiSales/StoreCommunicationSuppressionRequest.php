<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationSuppressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['required', 'integer', 'exists:unit_business_contexts,id'],
            'scope' => ['required', Rule::in(CommunicationSuppressionScope::values())],
            'unit_contact_context_link_id' => ['required_if:scope,endpoint', 'nullable', 'integer', 'exists:unit_contact_context_links,id'],
            'domain' => ['required_if:scope,domain', 'nullable', 'string', 'max:253', 'not_regex:/[\r\n\/@]/'],
            'reason' => ['required', Rule::in(CommunicationSuppressionReason::values())],
            'source' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.:\-]+$/'],
            'evidence_reference' => ['nullable', 'string', 'max:512', 'not_regex:/@|https?:\/\/|[\r\n]/i'],
            'evidence_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after:active_from'],
        ];
    }
}
