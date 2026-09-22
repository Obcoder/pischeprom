<?php

namespace App\Http\Requests\Mail\Concerns;

use Illuminate\Validation\Rule;
use JsonException;

trait ValidatesMailOffer
{
    protected function prepareForValidation(): void
    {
        // Multipart mail carries the structured offer as JSON so empty lists survive FormData.
        if (is_string($this->input('offer'))) {
            try {
                $this->merge(['offer' => json_decode($this->input('offer'), true, 32, JSON_THROW_ON_ERROR)]);
            } catch (JsonException) {
                // Keep malformed input unchanged; the array rule will return a normal 422 error.
            }
        }
    }

    protected function mailOfferRules(): array
    {
        return [
            'quoted_body' => ['nullable', 'string', 'max:100000'],
            'html' => ['prohibited'],
            'body_html' => ['prohibited'],
            'offer' => ['nullable', 'array:items,logistics'],
            'offer.items' => ['sometimes', 'array', 'list', 'max:10'],
            'offer.items.*' => ['required', 'array:good_id,quantity,price_override,include_description,include_specifications,include_image,specifications'],
            'offer.items.*.good_id' => ['required', 'integer', Rule::exists('goods', 'id')->where('is_published', true)],
            'offer.items.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'offer.items.*.price_override' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'offer.items.*.include_description' => ['sometimes', 'boolean'],
            'offer.items.*.include_specifications' => ['sometimes', 'boolean'],
            'offer.items.*.include_image' => ['sometimes', 'boolean'],
            'offer.items.*.specifications' => ['nullable', 'array', 'list', 'max:12'],
            'offer.items.*.specifications.*' => ['required', 'array:label,value'],
            'offer.items.*.specifications.*.label' => ['required', 'string', 'max:120'],
            'offer.items.*.specifications.*.value' => ['required', 'string', 'max:500'],
            'offer.logistics' => ['nullable', 'array:origin,destination,note,options', 'min:1'],
            'offer.logistics.origin' => ['required_with:offer.logistics', 'string', 'max:160'],
            'offer.logistics.destination' => ['required_with:offer.logistics', 'string', 'max:160'],
            'offer.logistics.note' => ['nullable', 'string', 'max:1000'],
            'offer.logistics.options' => ['required_with:offer.logistics', 'array', 'list', 'min:1', 'max:4'],
            'offer.logistics.options.*' => ['required', 'array:name,price,currency_code,duration,note'],
            'offer.logistics.options.*.name' => ['required', 'string', 'max:120'],
            'offer.logistics.options.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'offer.logistics.options.*.currency_code' => ['required', Rule::in(['RUB', 'USD', 'EUR'])],
            'offer.logistics.options.*.duration' => ['nullable', 'string', 'max:120'],
            'offer.logistics.options.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
