<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class CancelOutreachDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['reason_code' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9_.-]+\z/']];
    }
}
