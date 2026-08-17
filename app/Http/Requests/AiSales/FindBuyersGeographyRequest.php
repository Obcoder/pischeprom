<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class FindBuyersGeographyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['nullable', 'integer', 'min:1'],
            'region_id' => ['nullable', 'integer', 'min:1'],
            'provider' => ['prohibited'], 'contour' => ['prohibited'],
            'query' => ['prohibited'], 'url' => ['prohibited'],
        ];
    }
}
