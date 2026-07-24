<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use App\Domain\Banking\Events\PaymentAllocationCreated;
use App\Domain\Banking\Events\PaymentAllocationReversed;
use App\Domain\Banking\Events\ReceivablePaymentStatusChanged;
use App\Domain\Banking\Exceptions\ReconciliationConflictException;
use App\Models\BankTransaction;
use App\Models\BankTransactionAllocation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentAllocationService
{
    public function __construct(private readonly BankAuditLogger $audit) {}

    public function allocate(
        BankTransaction|int $transaction,
        Sale|int $sale,
        string|int $amount,
        AllocationSource $source,
        ?User $confirmedBy = null,
        ?string $matchingRule = null,
        ?string $comment = null,
    ): BankTransactionAllocation {
        $amount = DecimalMoney::normalize($amount);

        if (! DecimalMoney::isPositive($amount)) {
            throw new ReconciliationConflictException('Allocation amount must be positive.');
        }

        $statusChange = null;
        $allocation = DB::transaction(function () use (
            $transaction,
            $sale,
            $amount,
            $source,
            $confirmedBy,
            $matchingRule,
            $comment,
            &$statusChange,
        ): BankTransactionAllocation {
            $lockedTransaction = BankTransaction::query()
                ->whereKey($transaction instanceof BankTransaction ? $transaction->id : $transaction)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSale = Sale::query()
                ->whereKey($sale instanceof Sale ? $sale->id : $sale)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedTransaction->direction !== BankTransactionDirection::Credit
                || $lockedTransaction->status !== BankTransactionStatus::Posted
            ) {
                throw new ReconciliationConflictException('Only posted incoming transactions can be allocated.');
            }

            if ($lockedTransaction->no_reconciliation_required) {
                throw new ReconciliationConflictException('The transaction is marked as not requiring reconciliation.');
            }

            $unallocated = $this->transactionUnallocatedAmount($lockedTransaction);
            $outstanding = $this->saleOutstandingAmount($lockedSale);

            if (DecimalMoney::compare($amount, $unallocated) > 0) {
                throw new ReconciliationConflictException('Allocation exceeds the transaction unallocated balance.');
            }

            if (DecimalMoney::compare($amount, $outstanding) > 0) {
                throw new ReconciliationConflictException('Allocation exceeds the sale outstanding balance.');
            }

            $allocation = BankTransactionAllocation::query()->create([
                'bank_transaction_id' => $lockedTransaction->id,
                'allocatable_type' => $lockedSale->getMorphClass(),
                'allocatable_id' => $lockedSale->id,
                'amount' => $amount,
                'source' => $source,
                'matching_rule' => $matchingRule,
                'confirmed_by' => $confirmedBy?->id,
                'is_active' => true,
                'comment' => $comment,
            ]);

            $statusChange = $this->recalculateSaleLocked($lockedSale);
            $this->recalculateTransactionLocked($lockedTransaction);
            $this->audit->record('bank.allocation.created', $allocation, [
                'transaction_id' => $lockedTransaction->id,
                'sale_id' => $lockedSale->id,
                'amount' => $amount,
                'source' => $source->value,
                'matching_rule' => $matchingRule,
            ], $confirmedBy);

            return $allocation->fresh(['transaction', 'allocatable']);
        }, 3);

        PaymentAllocationCreated::dispatch($allocation);

        if ($statusChange !== null) {
            ReceivablePaymentStatusChanged::dispatch(
                $allocation->allocatable,
                $statusChange['previous'],
                $statusChange['current'],
            );
        }

        return $allocation;
    }

    public function reverse(
        BankTransactionAllocation|int $allocation,
        User $user,
        string $reason,
    ): BankTransactionAllocation {
        $statusChange = null;
        $allocation = DB::transaction(function () use ($allocation, $user, $reason, &$statusChange): BankTransactionAllocation {
            $locked = BankTransactionAllocation::query()
                ->whereKey($allocation instanceof BankTransactionAllocation ? $allocation->id : $allocation)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_active) {
                throw new ReconciliationConflictException('Allocation is already reversed.');
            }

            if ($locked->allocatable_type !== (new Sale)->getMorphClass()) {
                throw new ReconciliationConflictException('Only Sale allocations are supported in the first banking stage.');
            }

            $transaction = BankTransaction::query()->whereKey($locked->bank_transaction_id)->lockForUpdate()->firstOrFail();
            $sale = Sale::query()->whereKey($locked->allocatable_id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'is_active' => false,
                'reversed_by' => $user->id,
                'reversed_at' => now(),
                'reversal_reason' => mb_substr($reason, 0, 1024),
            ])->save();
            $statusChange = $this->recalculateSaleLocked($sale);
            $this->recalculateTransactionLocked($transaction);
            $transaction->forceFill([
                'reconciliation_status' => ReconciliationStatus::NeedsReview,
                'review_reason' => 'allocation_reversed',
            ])->save();
            $this->audit->record('bank.allocation.reversed', $locked, [
                'transaction_id' => $transaction->id,
                'sale_id' => $sale->id,
                'amount' => (string) $locked->amount,
                'reason' => mb_substr($reason, 0, 1024),
            ], $user);

            return $locked->fresh(['transaction', 'allocatable']);
        }, 3);

        PaymentAllocationReversed::dispatch($allocation);

        if ($statusChange !== null) {
            ReceivablePaymentStatusChanged::dispatch(
                $allocation->allocatable,
                $statusChange['previous'],
                $statusChange['current'],
            );
        }

        return $allocation;
    }

    public function reverseForChangedTransaction(BankTransaction $transaction, string $reason): int
    {
        $reversed = 0;

        DB::transaction(function () use ($transaction, $reason, &$reversed): void {
            $lockedTransaction = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $allocations = BankTransactionAllocation::query()
                ->where('bank_transaction_id', $lockedTransaction->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();
            $saleIds = $allocations->pluck('allocatable_id')->unique();

            foreach ($allocations as $allocation) {
                $allocation->forceFill([
                    'is_active' => false,
                    'reversed_at' => now(),
                    'reversal_reason' => mb_substr($reason, 0, 1024),
                ])->save();
                $reversed++;
                PaymentAllocationReversed::dispatch($allocation);
            }

            foreach ($saleIds as $saleId) {
                $sale = Sale::query()->whereKey($saleId)->lockForUpdate()->first();

                if ($sale) {
                    $change = $this->recalculateSaleLocked($sale);

                    if ($change) {
                        ReceivablePaymentStatusChanged::dispatch($sale, $change['previous'], $change['current']);
                    }
                }
            }

            $lockedTransaction->forceFill([
                'review_reason' => 'bank_operation_changed',
            ])->save();
            $this->recalculateTransactionLocked($lockedTransaction, ReconciliationStatus::NeedsReview);
            $this->audit->record('bank.allocations.reversed_for_transaction_change', $lockedTransaction, [
                'count' => $reversed,
                'reason' => $reason,
            ]);
        }, 3);

        return $reversed;
    }

    public function transactionUnallocatedAmount(BankTransaction $transaction): string
    {
        $allocated = (string) (DB::table('bank_transaction_allocations')
            ->where('bank_transaction_id', $transaction->id)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(amount), 0) AS aggregate')
            ->value('aggregate') ?? '0');

        return DecimalMoney::max(
            DecimalMoney::subtract((string) $transaction->amount, $allocated),
            '0.00'
        );
    }

    public function saleOutstandingAmount(Sale $sale): string
    {
        $paid = $this->salePaidAmount($sale);

        return DecimalMoney::max(
            DecimalMoney::subtract((string) $sale->total, $paid),
            '0.00'
        );
    }

    private function salePaidAmount(Sale $sale): string
    {
        return DecimalMoney::normalize((string) (DB::table('bank_transaction_allocations as allocations')
            ->join('bank_transactions as transactions', 'transactions.id', '=', 'allocations.bank_transaction_id')
            ->where('allocations.allocatable_type', $sale->getMorphClass())
            ->where('allocations.allocatable_id', $sale->id)
            ->where('allocations.is_active', true)
            ->where('transactions.status', BankTransactionStatus::Posted->value)
            ->selectRaw('COALESCE(SUM(allocations.amount), 0) AS aggregate')
            ->value('aggregate') ?? '0'));
    }

    private function recalculateSaleLocked(Sale $sale): ?array
    {
        $previous = (string) ($sale->payment_status ?: 'unpaid');
        $paid = $this->salePaidAmount($sale);
        $total = DecimalMoney::normalize((string) $sale->total);
        $outstanding = DecimalMoney::max(DecimalMoney::subtract($total, $paid), '0.00');
        $overpaid = DecimalMoney::max(DecimalMoney::subtract($paid, $total), '0.00');

        if (DecimalMoney::compare($paid, '0.00') === 0) {
            $status = 'unpaid';
        } elseif (DecimalMoney::compare($paid, $total) < 0) {
            $status = 'partially_paid';
        } elseif (DecimalMoney::compare($paid, $total) === 0) {
            $status = 'paid';
        } else {
            $status = 'overpaid';
        }

        $sale->forceFill([
            'paid_amount' => $paid,
            'outstanding_amount' => $outstanding,
            'overpaid_amount' => $overpaid,
            'payment_status' => $status,
            'paid_at' => in_array($status, ['paid', 'overpaid'], true)
                ? ($sale->paid_at ?? now())
                : null,
        ])->save();

        return $previous !== $status
            ? ['previous' => $previous, 'current' => $status]
            : null;
    }

    private function recalculateTransactionLocked(
        BankTransaction $transaction,
        ?ReconciliationStatus $forced = null,
    ): void {
        if ($forced) {
            $status = $forced;
        } elseif ($transaction->no_reconciliation_required) {
            $status = ReconciliationStatus::NotRequired;
        } else {
            $unallocated = $this->transactionUnallocatedAmount($transaction);
            $allocated = DecimalMoney::subtract((string) $transaction->amount, $unallocated);

            if (DecimalMoney::compare($allocated, '0.00') === 0) {
                $status = ReconciliationStatus::Unmatched;
            } elseif (DecimalMoney::compare($unallocated, '0.00') === 0) {
                $status = ReconciliationStatus::Allocated;
            } elseif ($transaction->review_reason === 'overpayment') {
                $status = ReconciliationStatus::Overpaid;
            } else {
                $status = ReconciliationStatus::PartiallyAllocated;
            }
        }

        $transaction->forceFill(['reconciliation_status' => $status])->save();
    }
}
