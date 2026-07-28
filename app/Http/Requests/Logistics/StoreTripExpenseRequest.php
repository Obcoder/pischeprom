<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsTripExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        return (bool) $this->user()?->can('create', LogisticsTripExpense::class);
    }

    public function rules(): array
    {
        return [
            'check_id' => ['nullable', 'integer', 'exists:checks,id'],
            'expense_category_id' => [
                'required', 'integer',
                Rule::exists('logistics_expense_categories', 'id')->where('is_active', true),
            ],
            'allocated_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha:ascii'],
            'occurred_at' => ['nullable', 'date'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'unit_price' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => mb_strtoupper((string) $this->input(
                'currency_code',
                config('logistics.currency_code', 'RUB')
            )),
            'unit' => filled($this->input('unit')) ? mb_strtolower(trim((string) $this->input('unit'))) : null,
        ]);
    }
}
