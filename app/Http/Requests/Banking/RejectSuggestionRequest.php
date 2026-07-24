<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class RejectSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bank.reconcile') ?? false;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
