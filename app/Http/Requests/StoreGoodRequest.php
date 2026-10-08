<?php

namespace App\Http\Requests;

use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'incoming_code' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:goods,slug'],
            'denominator' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'ava_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'prohibits:avatar_source_url,avatar_thumb_source_url'],
            'avatar_source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'avatar_thumb_source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'remove_ava' => ['nullable', 'boolean'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
            'industry_ids' => ['nullable', 'array'],
            'industry_ids.*' => ['integer', 'exists:industries,id'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['integer', 'exists:fields,id'],
            'entity_classification_ids' => ['nullable', 'array'],
            'entity_classification_ids.*' => ['integer', 'exists:entity_classifications,id'],
            ...GoodTradeCodes::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'ava_image.prohibits' => 'Выберите файл аватара или ссылку CDN.',
            'avatar_source_url.url' => 'Укажите полный URL аватара с https:// или http://.',
            'avatar_thumb_source_url.url' => 'Укажите полный URL миниатюры с https:// или http://.',
            '*.regex' => 'Неверный формат кода.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(GoodTradeCodes::normalize($this->all()));
    }
}
