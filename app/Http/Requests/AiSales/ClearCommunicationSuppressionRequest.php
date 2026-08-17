<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class ClearCommunicationSuppressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'safe_note' => ['nullable', 'string', 'max:500', 'not_regex:/@|https?:\/\/|[\r\n]/i'],
        ];
    }
}
