<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RejectProspectingCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('prospectingCandidate'));
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::in(['irrelevant', 'invalid_source', 'duplicate_ambiguous', 'policy_blocked'])],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
