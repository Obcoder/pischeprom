<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\EntityProposalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityCandidateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['required', 'integer', 'exists:unit_business_contexts,id'],
            'action' => ['required', Rule::enum(EntityProposalAction::class)],
            'existing_entity_id' => ['nullable', 'required_if:action,link_existing', 'prohibited_if:action,create', 'integer', 'exists:entities,id'],
            'proposed_name' => ['nullable', 'required_if:action,create', 'prohibited_if:action,link_existing', 'string', 'max:255'],
            'proposed_attributes' => ['nullable', 'array:full_name,entity_classification_id,INN,KPP,OGRN,legal_address,country_id'],
            'proposed_attributes.full_name' => ['nullable', 'string', 'max:1024'],
            'proposed_attributes.entity_classification_id' => ['nullable', 'integer', 'exists:entity_classifications,id'],
            'proposed_attributes.INN' => ['nullable', 'string', 'max:32'],
            'proposed_attributes.KPP' => ['nullable', 'string', 'max:32'],
            'proposed_attributes.OGRN' => ['nullable', 'string', 'max:32'],
            'proposed_attributes.legal_address' => ['nullable', 'string', 'max:1024'],
            'proposed_attributes.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'evidence_summary' => ['required', 'string', 'max:4000'],
        ];
    }
}
