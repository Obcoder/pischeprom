<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class FindBuyersDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'provider' => ['prohibited'],
            'model' => ['prohibited'],
            'contour' => ['prohibited'],
            'query' => ['prohibited'],
            'url' => ['prohibited'],
        ];
    }
}
