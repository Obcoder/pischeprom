<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class RunClientAcquisitionCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('operate', $this->route('clientAcquisitionCampaign'));
    }

    public function rules(): array
    {
        return [
            'idempotency_token' => ['required', 'uuid'],
            'workflow' => ['prohibited'], 'provider' => ['prohibited'], 'model' => ['prohibited'],
            'prompt' => ['prohibited'], 'query' => ['prohibited'], 'url' => ['prohibited'],
            'tool' => ['prohibited'], 'tools' => ['prohibited'], 'scheduler' => ['prohibited'],
            'live' => ['prohibited'], 'dispatch' => ['prohibited'], 'entity_id' => ['prohibited'],
        ];
    }
}
