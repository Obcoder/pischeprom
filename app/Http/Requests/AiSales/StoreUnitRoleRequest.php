<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\UnitRoleCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'role_code' => ['required', Rule::enum(UnitRoleCode::class)],
            'source' => ['nullable', 'string', 'max:64'],
        ];
    }
}
