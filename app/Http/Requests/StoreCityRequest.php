<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $nextYear = now()->year + 1;

        return [
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],

            'wiki' => ['nullable', 'string', 'max:2048'],
            'wiki_title' => ['nullable', 'string', 'max:255'],
            'wiki_summary' => ['nullable', 'string'],
            'wiki_thumbnail' => ['nullable', 'string', 'max:2048'],
            'wiki_page_id' => ['nullable', 'integer', 'min:1'],
            'wiki_lang' => ['nullable', 'string', 'max:8'],

            'population' => ['nullable', 'integer', 'min:0'],

            'yandexmapsgeo' => ['nullable', 'string', 'max:2048'],
            'twogis' => ['nullable', 'string', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'populations' => ['nullable', 'array'],
            'populations.*.year' => ['required_with:populations', 'integer', "between:1,{$nextYear}"],
            'populations.*.population' => ['required_with:populations', 'integer', 'min:0'],
        ];
    }
}
