<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIndustryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('industries', 'code')
                    ->ignore($this->route('industry')->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => trim($this->code)]);
        }

        if ($this->has('title')) {
            $this->merge(['title' => trim($this->title)]);
        }
    }
}
