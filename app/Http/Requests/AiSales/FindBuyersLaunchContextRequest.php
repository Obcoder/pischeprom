<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FindBuyersLaunchContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(['product', 'good'])],
            'source_id' => ['required', 'integer', 'min:1'],
            'selected_product_id' => ['nullable', 'integer', 'min:1'],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'query' => ['prohibited'], 'url' => ['prohibited'],
            'tool' => ['prohibited'], 'execute' => ['prohibited'],
        ];
    }
}
