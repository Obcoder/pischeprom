<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitBusinessContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lane' => ['required', Rule::enum(BusinessLane::class)],
            'role_code' => ['required', Rule::enum(UnitRoleCode::class)],
            'stage' => ['nullable', Rule::enum(UnitContextStage::class)],
            'status' => ['nullable', Rule::enum(UnitContextStatus::class)],
            'confidence' => ['nullable', 'integer', 'between:0,100'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'primary_good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'primary_segment' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
            'first_activity_at' => ['nullable', 'date'],
            'last_activity_at' => ['nullable', 'date'],
        ];
    }
}
