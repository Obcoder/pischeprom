<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProspectingScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['reviewed', 'rejected'])],
            'weight' => ['prohibited'], 'weights' => ['prohibited'], 'formula' => ['prohibited'],
            'factor' => ['prohibited'], 'factor_code' => ['prohibited'], 'provider' => ['prohibited'],
            'model' => ['prohibited'], 'prompt' => ['prohibited'], 'url' => ['prohibited'],
            'computed_score' => ['prohibited'], 'effective_score' => ['prohibited'],
            'confidence' => ['prohibited'], 'band' => ['prohibited'], 'eligibility' => ['prohibited'],
        ];
    }
}
