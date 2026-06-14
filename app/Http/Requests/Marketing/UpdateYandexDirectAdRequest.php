<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateYandexDirectAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title_1' => ['required', 'string', 'max:255'],
            'title_2' => ['nullable', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'href' => ['required', 'url'],
            'utm_template' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'status' => ['nullable', 'string', 'in:draft,ready,syncing,sent,moderation,active,suspended,error'],
            'keywords' => ['nullable', 'array'],
            'keywords.*.id' => ['nullable', 'integer', 'exists:yandex_direct_keywords,id'],
            'keywords.*.phrase' => ['required_with:keywords', 'string'],
            'keywords.*.bid' => ['nullable', 'numeric', 'min:0'],
            'keywords.*.context_bid' => ['nullable', 'numeric', 'min:0'],
            'keywords.*.is_negative' => ['nullable', 'boolean'],
        ];
    }
}
