<?php

namespace App\Http\Requests\AiPriceLists;

use App\Models\PriceListImportItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PriceListImportItem|null $item */
        $item = $this->route('priceListItem');

        return $item && ($this->user()?->can('review', $item->import) ?? false);
    }

    public function rules(): array
    {
        $decimal = ['nullable', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/'];

        return [
            'raw_name' => ['sometimes', 'required', 'string', 'max:255'],
            'supplier_sku' => ['sometimes', 'nullable', 'string', 'max:191'],
            'manufacturer_sku' => ['sometimes', 'nullable', 'string', 'max:191'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_of_origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'package_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'units_per_package' => ['sometimes', ...$decimal],
            'net_quantity' => ['sometimes', ...$decimal],
            'net_quantity_unit' => ['sometimes', 'nullable', Rule::in(['kg', 'g', 'l', 'ml', 'pcs', 'box'])],
            'price_basis_quantity' => ['sometimes', ...$decimal],
            'price_basis_unit' => ['sometimes', 'nullable', Rule::in(['kg', 'g', 'l', 'ml', 'pcs', 'box'])],
            'minimum_order_quantity' => ['sometimes', ...$decimal],
            'price' => ['sometimes', 'nullable', 'regex:/^[0-9]+(?:\.[0-9]{1,6})?$/', 'not_regex:/^0+(?:\.0+)?$/'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'vat_mode' => ['sometimes', Rule::in(['included', 'excluded', 'unknown'])],
            'vat_rate' => ['sometimes', 'nullable', 'regex:/^[0-9]{1,2}(?:\.[0-9]{1,4})?$/'],
            'availability' => ['sometimes', 'nullable', 'string', 'max:255'],
            'valid_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'valid_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['valid_from', 'valid_to'])) {
                return;
            }

            /** @var PriceListImportItem|null $item */
            $item = $this->route('priceListItem');
            $from = array_key_exists('valid_from', $this->all())
                ? $this->input('valid_from')
                : $item?->valid_from?->format('Y-m-d');
            $to = array_key_exists('valid_to', $this->all())
                ? $this->input('valid_to')
                : $item?->valid_to?->format('Y-m-d');

            if (is_string($from) && is_string($to) && $to < $from) {
                $validator->errors()->add('valid_to', 'Дата окончания не может быть раньше даты начала.');
            }
        }];
    }
}
