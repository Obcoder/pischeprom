<?php

namespace App\Http\Requests\AiPriceLists;

use App\Models\PriceListImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkPriceListDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PriceListImport|null $import */
        $import = $this->route('priceListImport');

        return $import && Gate::allows('review', $import);
    }

    public function rules(): array
    {
        return [
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'vat_mode' => ['nullable', Rule::in(['included', 'excluded', 'unknown'])],
            'vat_rate' => ['nullable', 'regex:/^[0-9]{1,2}(?:\.[0-9]{1,4})?$/'],
            'preview' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (! $this->filled('currency_code') && ! $this->filled('vat_mode') && ! $this->filled('vat_rate')) {
                $validator->errors()->add('defaults', 'Укажите хотя бы одно значение по умолчанию.');
            }
        }];
    }
}
