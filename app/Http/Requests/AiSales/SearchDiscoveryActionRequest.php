<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class SearchDiscoveryActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'query' => ['prohibited'],
            'url' => ['prohibited'],
            'provider' => ['prohibited'],
            'profile' => ['prohibited'],
            'model' => ['prohibited'],
            'contour' => ['prohibited'],
            'prompt' => ['prohibited'],
            'tool' => ['prohibited'],
            'tools' => ['prohibited'],
            'max_results' => ['prohibited'],
            'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
