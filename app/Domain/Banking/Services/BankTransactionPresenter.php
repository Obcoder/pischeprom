<?php

namespace App\Domain\Banking\Services;

use App\Models\BankTransaction;

class BankTransactionPresenter
{
    public function summary(BankTransaction $transaction, bool $sensitive): array
    {
        $allocated = DecimalMoney::normalize((string) ($transaction->allocated_amount ?? '0'));
        $unallocated = DecimalMoney::max(
            DecimalMoney::subtract((string) $transaction->amount, $allocated),
            '0.00'
        );

        return [
            'id' => $transaction->id,
            'account_id' => $transaction->bank_account_id,
            'account' => $transaction->account ? [
                'id' => $transaction->account->id,
                'name' => $transaction->account->name,
                'number' => $sensitive
                    ? $transaction->account->account_number
                    : $transaction->account->masked_number,
            ] : null,
            'operation_date' => $transaction->operation_date?->toDateString(),
            'posting_date' => $transaction->posting_date?->toDateString(),
            'value_date' => $transaction->value_date?->toDateString(),
            'direction' => $transaction->direction->value,
            'amount' => $transaction->amount,
            'allocated_amount' => $allocated,
            'unallocated_amount' => $unallocated,
            'currency' => $transaction->currency,
            'status' => $transaction->status->value,
            'bank_document_number' => $transaction->bank_document_number,
            'purpose' => $transaction->purpose,
            'payer_name' => $transaction->payer_name,
            'payer_inn' => $transaction->payer_inn,
            'payer_kpp' => $transaction->payer_kpp,
            'payer_account' => $sensitive
                ? $transaction->payer_account
                : BankAccountMasker::mask($transaction->payer_account),
            'recipient_name' => $transaction->recipient_name,
            'recipient_inn' => $transaction->recipient_inn,
            'recipient_kpp' => $transaction->recipient_kpp,
            'recipient_account' => $sensitive
                ? $transaction->recipient_account
                : BankAccountMasker::mask($transaction->recipient_account),
            'entity' => $transaction->entity ? [
                'id' => $transaction->entity->id,
                'name' => $transaction->entity->name,
            ] : null,
            'reconciliation_status' => $transaction->reconciliation_status->value,
            'no_reconciliation_required' => $transaction->no_reconciliation_required,
            'review_reason' => $transaction->review_reason,
            'manager_comment' => $transaction->manager_comment,
            'bank_modified_at' => $transaction->bank_modified_at?->toISOString(),
            'imported_at' => $transaction->imported_at?->toISOString(),
            'created_at' => $transaction->created_at?->toISOString(),
            'updated_at' => $transaction->updated_at?->toISOString(),
        ];
    }

    public function detail(BankTransaction $transaction, bool $sensitive): array
    {
        return [
            ...$this->summary($transaction, $sensitive),
            'payer_bank' => [
                'name' => $transaction->payer_bank_name,
                'bic' => $transaction->payer_bic,
                'corr_account' => $sensitive
                    ? $transaction->payer_corr_account
                    : BankAccountMasker::mask($transaction->payer_corr_account),
            ],
            'recipient_bank' => [
                'name' => $transaction->recipient_bank_name,
                'bic' => $transaction->recipient_bic,
                'corr_account' => $sensitive
                    ? $transaction->recipient_corr_account
                    : BankAccountMasker::mask($transaction->recipient_corr_account),
            ],
            'allocations' => $transaction->allocations->map(fn ($allocation): array => [
                'id' => $allocation->id,
                'sale_id' => $allocation->allocatable_id,
                'sale' => $allocation->allocatable ? [
                    'id' => $allocation->allocatable->id,
                    'number' => $allocation->allocatable->payment_reference ?: (string) $allocation->allocatable->id,
                    'total' => $allocation->allocatable->total,
                    'payment_status' => $allocation->allocatable->payment_status,
                ] : null,
                'amount' => $allocation->amount,
                'source' => $allocation->source->value,
                'matching_rule' => $allocation->matching_rule,
                'is_active' => $allocation->is_active,
                'confirmed_by' => $allocation->confirmedBy?->name,
                'reversed_at' => $allocation->reversed_at?->toISOString(),
                'reversal_reason' => $allocation->reversal_reason,
                'comment' => $allocation->comment,
                'created_at' => $allocation->created_at?->toISOString(),
            ])->values(),
            'suggestions' => $transaction->suggestions->map(fn ($suggestion): array => [
                'id' => $suggestion->id,
                'sale_id' => $suggestion->suggestable_id,
                'sale' => $suggestion->suggestable ? [
                    'id' => $suggestion->suggestable->id,
                    'number' => $suggestion->suggestable->payment_reference ?: (string) $suggestion->suggestable->id,
                    'total' => $suggestion->suggestable->total,
                    'paid_amount' => $suggestion->suggestable->paid_amount,
                    'outstanding_amount' => $suggestion->suggestable->outstanding_amount,
                    'payment_status' => $suggestion->suggestable->payment_status,
                    'entity' => $suggestion->suggestable->entity ? [
                        'id' => $suggestion->suggestable->entity->id,
                        'name' => $suggestion->suggestable->entity->name,
                    ] : null,
                ] : null,
                'score' => $suggestion->score,
                'algorithm_version' => $suggestion->algorithm_version,
                'rules' => $suggestion->rules,
                'status' => $suggestion->status->value,
                'created_at' => $suggestion->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
