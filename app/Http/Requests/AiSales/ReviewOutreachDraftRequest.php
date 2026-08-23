<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewOutreachDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'revision_id' => ['required', 'integer', 'exists:outreach_draft_revisions,id'],
            'review_type' => ['required', Rule::in(OutreachReviewType::values())],
            'decision' => ['required', Rule::in(OutreachReviewDecision::values())],
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'safe_note' => ['nullable', 'string', 'max:500', 'not_regex:/@|https?:\/\/|[\r\n]/i'],
            'send' => ['prohibited'], 'dispatch' => ['prohibited'],
        ];
    }
}
