<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankTransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bank.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'direction' => ['nullable', Rule::in(['credit', 'debit'])],
            'amount_min' => ['nullable', 'regex:/^\d{1,18}(?:[.,]\d{1,2})?$/'],
            'amount_max' => ['nullable', 'regex:/^\d{1,18}(?:[.,]\d{1,2})?$/'],
            'entity' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'regex:/^\d{4,12}$/'],
            'purpose' => ['nullable', 'string', 'max:512'],
            'status' => ['nullable', Rule::in(['pending', 'posted', 'cancelled', 'reversed', 'unknown'])],
            'reconciliation_status' => ['nullable', Rule::in([
                'unmatched',
                'suggested',
                'partially_allocated',
                'allocated',
                'overpaid',
                'not_required',
                'needs_review',
            ])],
            'worklist' => ['nullable', Rule::in(['linked', 'partial_overpaid'])],
            'warning' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in([
                'operation_date',
                'amount',
                'direction',
                'status',
                'reconciliation_status',
                'created_at',
            ])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
