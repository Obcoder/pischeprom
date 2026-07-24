<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class SberOAuthCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'min:32', 'max:512'],
            'code' => ['nullable', 'string', 'max:4096', 'required_without:error'],
            'error' => ['nullable', 'string', 'max:128'],
            'error_description' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
