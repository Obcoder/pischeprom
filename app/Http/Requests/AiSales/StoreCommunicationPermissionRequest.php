<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Outreach\Enums\CommunicationEvidenceType;
use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['required', 'integer', 'exists:unit_business_contexts,id'],
            'unit_contact_context_link_id' => ['required', 'integer', 'exists:unit_contact_context_links,id'],
            'purpose' => ['required', Rule::in([MessagePurpose::AdvertisingOutreach->value])],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_category_scope' => ['nullable', 'string', 'max:128', 'not_regex:/[\r\n]/'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*' => ['array:type,reference,content_hash,captured_at,source_controller,safe_note'],
            'evidence.*.type' => ['required', Rule::in(CommunicationEvidenceType::values())],
            'evidence.*.reference' => ['required', 'string', 'max:512', 'not_regex:/@|https?:\/\/|[\r\n]/i'],
            'evidence.*.content_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'evidence.*.captured_at' => ['required', 'date', 'before_or_equal:now'],
            'evidence.*.source_controller' => ['nullable', 'string', 'max:128', 'regex:/^[a-zA-Z0-9_.:\-]+$/'],
            'evidence.*.safe_note' => ['nullable', 'string', 'max:500', 'not_regex:/@|https?:\/\/|[\r\n]/i'],
            'consent' => ['prohibited'], 'email' => ['prohibited'], 'recipient' => ['prohibited'],
        ];
    }
}
