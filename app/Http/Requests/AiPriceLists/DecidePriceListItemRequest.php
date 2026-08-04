<?php

namespace App\Http\Requests\AiPriceLists;

use App\Models\PriceListImportItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidePriceListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PriceListImportItem|null $item */
        $item = $this->route('priceListItem');

        return $item && ($this->user()?->can('review', $item->import) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['matched', 'create_draft', 'ignored', 'unreviewed'])],
            'good_id' => ['nullable', 'integer', 'exists:goods,id', 'required_if:decision,matched'],
            'save_alias' => ['sometimes', 'boolean'],
        ];
    }
}
