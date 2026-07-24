<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Enums\MatchSuggestionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Exceptions\ReconciliationConflictException;
use App\Models\BankMatchSuggestion;
use App\Models\BankTransaction;
use App\Models\BankTransactionAllocation;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualBankReconciliationService
{
    public function __construct(
        private readonly PaymentAllocationService $allocations,
        private readonly BankAuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array{sale_id:int,amount:string}>  $items
     */
    public function allocate(BankTransaction $transaction, array $items, User $user, ?string $comment): BankTransaction
    {
        if ($items === []) {
            throw new ReconciliationConflictException('At least one allocation is required.');
        }

        $saleIds = array_map(static fn (array $item): int => (int) $item['sale_id'], $items);

        if (count($saleIds) !== count(array_unique($saleIds))) {
            throw new ReconciliationConflictException('A sale may occur only once in one allocation request.');
        }

        DB::transaction(function () use ($transaction, $items, $saleIds, $user, $comment): void {
            $locked = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $wasOverpaid = $locked->reconciliation_status === ReconciliationStatus::Overpaid;

            foreach ($items as $item) {
                $sale = Sale::query()->whereKey((int) $item['sale_id'])->lockForUpdate()->firstOrFail();
                $this->allocations->allocate(
                    transaction: $locked,
                    sale: $sale,
                    amount: (string) $item['amount'],
                    source: AllocationSource::Manual,
                    confirmedBy: $user,
                    matchingRule: 'manual',
                    comment: $comment,
                );
                BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $locked->id)
                    ->where('suggestable_type', $sale->getMorphClass())
                    ->where('suggestable_id', $sale->id)
                    ->where('status', MatchSuggestionStatus::Pending->value)
                    ->update(['status' => MatchSuggestionStatus::Accepted->value]);
            }

            $locked->refresh();

            if ($comment !== null && trim($comment) !== '') {
                $locked->forceFill(['manager_comment' => $comment])->save();
            }

            $locked->forceFill(['review_reason' => null])->save();
            $unallocated = $this->allocations->transactionUnallocatedAmount($locked);
            $allSelectedSalesSettled = Sale::query()
                ->whereKey($saleIds)
                ->whereNotIn('payment_status', ['paid', 'overpaid'])
                ->doesntExist();

            if (DecimalMoney::isPositive($unallocated) && $allSelectedSalesSettled) {
                $locked->forceFill([
                    'reconciliation_status' => ReconciliationStatus::Overpaid,
                    'review_reason' => 'overpayment',
                ])->save();
                BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $locked->id)
                    ->where('status', MatchSuggestionStatus::Pending->value)
                    ->update(['status' => MatchSuggestionStatus::Expired->value]);

                if (! $wasOverpaid) {
                    $this->audit->record('bank.transaction.overpayment_detected', $locked, [
                        'source' => 'manual',
                        'unallocated_amount' => $unallocated,
                    ], $user);
                    $connection = $locked->connection()->first();

                    if ($connection) {
                        BankConnectionRequiresAttention::dispatch($connection, 'overpayment');
                    }
                }
            } elseif ($locked->reconciliation_status === ReconciliationStatus::Allocated) {
                $locked->forceFill(['review_reason' => null])->save();
                BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $locked->id)
                    ->where('status', MatchSuggestionStatus::Pending->value)
                    ->update(['status' => MatchSuggestionStatus::Expired->value]);
            }
        }, 3);

        return $transaction->fresh(['allocations.allocatable', 'suggestions.suggestable']);
    }

    public function reverse(
        BankTransactionAllocation $allocation,
        User $user,
        string $reason,
    ): BankTransactionAllocation {
        return $this->allocations->reverse($allocation, $user, $reason);
    }

    public function rejectSuggestion(
        BankTransaction $transaction,
        BankMatchSuggestion $suggestion,
        User $user,
        ?string $comment = null,
    ): BankMatchSuggestion {
        return DB::transaction(function () use ($transaction, $suggestion, $user, $comment): BankMatchSuggestion {
            $lockedTransaction = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $lockedSuggestion = BankMatchSuggestion::query()
                ->whereKey($suggestion->id)
                ->where('bank_transaction_id', $lockedTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSuggestion->status !== MatchSuggestionStatus::Pending) {
                throw new ReconciliationConflictException('Only a pending suggestion can be rejected.');
            }

            $lockedSuggestion->forceFill(['status' => MatchSuggestionStatus::Rejected])->save();

            if (
                ! BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $lockedTransaction->id)
                    ->where('status', MatchSuggestionStatus::Pending->value)
                    ->exists()
                && ! $lockedTransaction->activeAllocations()->exists()
            ) {
                $lockedTransaction->forceFill([
                    'reconciliation_status' => ReconciliationStatus::Unmatched,
                    'review_reason' => null,
                ])->save();
            }

            $this->audit->record('bank.suggestion.rejected', $lockedSuggestion, [
                'transaction_id' => $lockedTransaction->id,
                'comment' => $comment,
            ], $user);

            return $lockedSuggestion->fresh();
        }, 3);
    }

    public function markNotRequired(
        BankTransaction $transaction,
        User $user,
        string $comment,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $user, $comment): BankTransaction {
            $locked = BankTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->activeAllocations()->exists()) {
                throw new ReconciliationConflictException('Reverse active allocations before marking the operation as not required.');
            }

            $locked->forceFill([
                'no_reconciliation_required' => true,
                'reconciliation_status' => ReconciliationStatus::NotRequired,
                'manager_comment' => $comment,
                'review_reason' => null,
            ])->save();
            BankMatchSuggestion::query()
                ->where('bank_transaction_id', $locked->id)
                ->where('status', MatchSuggestionStatus::Pending->value)
                ->update(['status' => MatchSuggestionStatus::Expired->value]);
            $this->audit->record('bank.transaction.reconciliation_not_required', $locked, [
                'comment' => $comment,
            ], $user);

            return $locked->fresh();
        }, 3);
    }
}
