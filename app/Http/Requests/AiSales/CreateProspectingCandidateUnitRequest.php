<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateProspectingCandidateUnitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reviewed_working_name'))) {
            $this->merge(['reviewed_working_name' => trim($this->input('reviewed_working_name'))]);
        }
    }

    public function authorize(): bool
    {
        return Gate::allows('resolve', $this->route('prospectingCandidate'));
    }

    public function rules(): array
    {
        return [
            'reviewed_working_name' => ['bail', 'required', 'string', 'min:2', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'name_confirmed' => ['required', 'accepted'],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
