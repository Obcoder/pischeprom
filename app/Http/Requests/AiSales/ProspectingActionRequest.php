<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProspectingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()?->getName()) {
            'api.ai-sales.prospecting.jobs.submit',
            'api.ai-sales.prospecting.jobs.cancel' => Gate::allows('update', $this->route('prospectingSearchJob')),
            'api.ai-sales.prospecting.jobs.approve',
            'api.ai-sales.prospecting.jobs.archive' => Gate::allows('review', $this->route('prospectingSearchJob')),
            'api.ai-sales.prospecting.candidates.evaluate' => Gate::allows('review', $this->route('prospectingCandidate')),
            'api.ai-sales.prospecting.candidates.create-unit' => Gate::allows('resolve', $this->route('prospectingCandidate')),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
