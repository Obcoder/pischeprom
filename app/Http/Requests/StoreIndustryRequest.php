<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIndustryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // позже можно подключить Policy
    }

    public function rules(): array
    {
        return [
            'code'  => ['required', 'string', 'max:20', 'unique:industries,code'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
                         'code'  => trim($this->code),
                         'title' => trim($this->title),
                     ]);
    }
}
