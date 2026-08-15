<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewUnitObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::enum(ObservationVerificationStatus::class)],
        ];
    }
}
