<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankPaymentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('bank.manage_payment_drafts') ?? false)
            && ($this->user()?->can('bank.view_sensitive') ?? false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['amount', 'vat_amount'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => str_replace([' ', ','], ['', '.'], $value)]);
            }
        }
    }

    public function rules(): array
    {
        $draftId = $this->route('draft')?->id;

        return [
            'number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('bank_payment_order_drafts', 'number')
                    ->where(fn ($query) => $query->where('document_date', $this->input('document_date')))
                    ->ignore($draftId),
            ],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'payer_bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'recipient_entity_id' => ['required', 'integer', 'exists:entities,id'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'amount' => ['required', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'payer_name' => ['required', 'string', 'max:1024'],
            'payer_inn' => ['required', 'regex:/^(?:\d{10}|\d{12})$/'],
            'payer_kpp' => ['nullable', 'regex:/^\d{9}$/'],
            'payer_account' => ['required', 'regex:/^\d{20}$/'],
            'payer_bank_name' => ['required', 'string', 'max:1024'],
            'payer_bic' => ['required', 'regex:/^\d{9}$/'],
            'payer_corr_account' => ['required', 'regex:/^\d{20}$/'],
            'recipient_name' => ['required', 'string', 'max:1024'],
            'recipient_inn' => ['required', 'regex:/^(?:\d{10}|\d{12})$/'],
            'recipient_kpp' => ['nullable', 'regex:/^\d{9}$/'],
            'recipient_account' => ['required', 'regex:/^\d{20}$/'],
            'recipient_bank_name' => ['required', 'string', 'max:1024'],
            'recipient_bic' => ['required', 'regex:/^\d{9}$/'],
            'recipient_corr_account' => ['required', 'regex:/^\d{20}$/'],
            'purpose' => ['required', 'string', 'min:3', 'max:210'],
            'vat_type' => ['required', Rule::in(['included', 'on_top', 'without_vat'])],
            'vat_rate' => ['nullable', Rule::in(['0', '5', '7', '10', '20'])],
            'vat_amount' => ['nullable', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'payment_priority' => ['required', 'integer', 'between:1,5'],
            'budget_fields' => ['nullable', 'array:kbk,oktmo,payment_basis,tax_period,document_number,document_date,uin'],
            'budget_fields.*' => ['nullable', 'string', 'max:64'],
            'card_number' => ['prohibited'],
            'pan' => ['prohibited'],
            'cvv' => ['prohibited'],
            'cvc' => ['prohibited'],
        ];
    }
}
