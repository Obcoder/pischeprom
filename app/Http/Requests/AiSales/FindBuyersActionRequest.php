<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class FindBuyersActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'query' => ['prohibited'], 'provider' => ['prohibited'], 'profile' => ['prohibited'],
            'model' => ['prohibited'], 'contour' => ['prohibited'], 'prompt' => ['prohibited'],
            'url' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'], 'entity_id' => ['prohibited'],
        ];
    }
}
