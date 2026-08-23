<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

final class ClientAcquisitionCampaignActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'workflow' => ['prohibited'], 'provider' => ['prohibited'], 'model' => ['prohibited'],
            'prompt' => ['prohibited'], 'query' => ['prohibited'], 'url' => ['prohibited'],
            'tool' => ['prohibited'], 'tools' => ['prohibited'], 'scheduler' => ['prohibited'],
            'dispatch' => ['prohibited'], 'entity_id' => ['prohibited'], 'consent' => ['prohibited'],
        ];
    }
}
