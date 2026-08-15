<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiAgentRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'definition_code' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9_.-]+$/'],
            'definition_version' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'unit_business_context_id' => ['required', 'integer', 'exists:unit_business_contexts,id'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
        ];
    }
}
