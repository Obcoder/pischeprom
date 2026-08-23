<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class PrepareOutreachDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'recipient' => ['prohibited'], 'body' => ['prohibited'], 'html' => ['prohibited'],
            'provider' => ['prohibited'], 'url' => ['prohibited'], 'headers' => ['prohibited'],
            'from' => ['prohibited'], 'reply_to' => ['prohibited'],
        ];
    }
}
