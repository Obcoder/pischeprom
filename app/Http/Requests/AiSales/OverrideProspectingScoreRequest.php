<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideProspectingScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_score' => ['required', 'integer', 'between:0,100'],
            'reason_code' => ['required', Rule::in(['human_evidence_correction', 'data_quality_correction', 'temporary_priority_review', 'review_disagreement'])],
            'safe_note' => ['required', 'string', 'min:3', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays(90)->toISOString()],
            'weight' => ['prohibited'], 'weights' => ['prohibited'], 'formula' => ['prohibited'],
            'factor' => ['prohibited'], 'factor_code' => ['prohibited'], 'provider' => ['prohibited'],
            'model' => ['prohibited'], 'prompt' => ['prohibited'], 'url' => ['prohibited'],
            'computed_score' => ['prohibited'], 'confidence' => ['prohibited'],
            'band' => ['prohibited'], 'eligibility' => ['prohibited'],
        ];
    }
}
