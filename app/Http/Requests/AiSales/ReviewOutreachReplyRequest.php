<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewOutreachReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::enum(OutreachReplyClass::class)],
            'reason_code' => ['required', 'string', 'max:64', 'regex:/\A[a-z0-9_.-]+\z/'],
            'response' => ['prohibited'], 'body' => ['prohibited'], 'auto_reply' => ['prohibited'],
        ];
    }
}
