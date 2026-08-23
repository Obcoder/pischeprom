<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Outreach\Enums\MessagePurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutreachDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_business_context_id' => ['required', 'integer', 'exists:unit_business_contexts,id'],
            'unit_contact_context_link_id' => ['nullable', 'integer', 'exists:unit_contact_context_links,id'],
            'unit_product_match_id' => ['required', 'integer', 'exists:unit_product_matches,id'],
            'unit_good_match_id' => ['nullable', 'integer', 'exists:unit_good_matches,id'],
            'product_relevance_snapshot_id' => ['nullable', 'integer', 'exists:unit_product_relevance_snapshots,id'],
            'good_fit_snapshot_id' => ['nullable', 'integer', 'exists:unit_good_fit_snapshots,id'],
            'prospect_priority_snapshot_id' => ['nullable', 'integer', 'exists:unit_prospect_priority_snapshots,id'],
            'purpose' => ['required', Rule::in([MessagePurpose::AdvertisingOutreach->value])],
            'prompt' => ['prohibited'], 'provider' => ['prohibited'], 'model' => ['prohibited'],
            'url' => ['prohibited'], 'html' => ['prohibited'], 'candidate_id' => ['prohibited'],
            'send' => ['prohibited'], 'dispatch' => ['prohibited'], 'recipient_email' => ['prohibited'],
        ];
    }
}
