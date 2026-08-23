<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class RecalculateProspectingScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight' => ['prohibited'], 'weights' => ['prohibited'], 'formula' => ['prohibited'],
            'factor' => ['prohibited'], 'factor_code' => ['prohibited'], 'provider' => ['prohibited'],
            'model' => ['prohibited'], 'prompt' => ['prohibited'], 'url' => ['prohibited'],
            'computed_score' => ['prohibited'], 'effective_score' => ['prohibited'],
            'confidence' => ['prohibited'], 'band' => ['prohibited'], 'eligibility' => ['prohibited'],
        ];
    }
}
