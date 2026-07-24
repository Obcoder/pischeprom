<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class BankCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bank.reconcile') ?? false;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
