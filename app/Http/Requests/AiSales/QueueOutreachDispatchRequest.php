<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class QueueOutreachDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'recipient' => ['prohibited'], 'subject' => ['prohibited'], 'body' => ['prohibited'],
            'html' => ['prohibited'], 'provider' => ['prohibited'], 'url' => ['prohibited'],
            'headers' => ['prohibited'], 'from' => ['prohibited'], 'reply_to' => ['prohibited'],
        ];
    }
}
