<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class ReviseOutreachDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'structured_content' => ['required', 'array:subject,greeting,introduction,value_proposition,evidence_points,call_to_action,closing,claims'],
            'structured_content.subject' => ['required', 'string', 'max:160'],
            'structured_content.greeting' => ['required', 'string', 'max:160'],
            'structured_content.introduction' => ['required', 'string', 'max:1000'],
            'structured_content.value_proposition' => ['required', 'string', 'max:2000'],
            'structured_content.evidence_points' => ['required', 'array', 'max:10'],
            'structured_content.evidence_points.*' => ['string', 'max:1000'],
            'structured_content.call_to_action' => ['required', 'string', 'max:1000'],
            'structured_content.closing' => ['required', 'string', 'max:500'],
            'structured_content.claims' => ['required', 'array', 'max:10'],
            'structured_content.claims.*' => ['array:type,text,evidence_type,evidence_reference,evidence_hash'],
            'structured_content.claims.*.type' => ['required', 'in:product_relevance,good_offer_fit'],
            'structured_content.claims.*.text' => ['required', 'string', 'max:500'],
            'structured_content.claims.*.evidence_type' => ['required', 'in:unit_product_match,unit_good_match'],
            'structured_content.claims.*.evidence_reference' => ['required', 'string', 'max:512'],
            'structured_content.claims.*.evidence_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'prompt' => ['prohibited'], 'provider' => ['prohibited'], 'model' => ['prohibited'],
            'url' => ['prohibited'], 'html' => ['prohibited'],
            'send' => ['prohibited'], 'dispatch' => ['prohibited'], 'recipient_email' => ['prohibited'],
        ];
    }
}
