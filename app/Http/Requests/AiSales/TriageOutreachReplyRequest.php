<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Foundation\Http\FormRequest;

class TriageOutreachReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['prompt' => ['prohibited'], 'provider' => ['prohibited'], 'auto_reply' => ['prohibited']];
    }
}
