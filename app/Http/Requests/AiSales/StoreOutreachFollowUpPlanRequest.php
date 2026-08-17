<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutreachFollowUpPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'schedule_at' => ['prohibited'], 'send_at' => ['prohibited'], 'auto_send' => ['prohibited'],
            'body' => ['prohibited'], 'recipient' => ['prohibited'],
        ];
    }
}
