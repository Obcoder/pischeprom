<?php

namespace App\Domain\Banking\Reconciliation;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\MatchSuggestionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use App\Domain\Banking\Events\BankConnectionRequiresAttention;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Domain\Banking\Services\DecimalMoney;
use App\Domain\Banking\Services\PaymentAllocationService;
use App\Models\BankMatchSuggestion;
use App\Models\BankTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    public function __construct(
        private readonly BankTransactionMatcher $matcher,
        private readonly PaymentAllocationService $allocations,
        private readonly BankAuditLogger $audit,
    ) {}

    public function reconcile(BankTransaction|int $transaction): BankTransaction
    {
        $transactionId = $transaction instanceof BankTransaction
            ? $transaction->id
            : $transaction;

        return DB::transaction(function () use ($transactionId): BankTransaction {
            $transaction = BankTransaction::query()
                ->with('connection')
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->direction !== BankTransactionDirection::Credit) {
                $transaction->forceFill([
                    'reconciliation_status' => ReconciliationStatus::NotRequired,
                    'no_reconciliation_required' => true,
                ])->save();

                return $transaction->fresh();
            }

            if ($transaction->status !== BankTransactionStatus::Posted) {
                $transaction->forceFill([
                    'reconciliation_status' => ReconciliationStatus::NeedsReview,
                    'review_reason' => 'operation_not_posted',
                ])->save();

                return $transaction->fresh();
            }

            if ($transaction->reconciliation_status === ReconciliationStatus::NeedsReview) {
                return $transaction;
            }

            if ($transaction->activeAllocations()->exists()) {
                return $transaction;
            }

            $result = $this->matcher->match($transaction);
            $rejectedSaleIds = BankMatchSuggestion::query()
                ->where('bank_transaction_id', $transaction->id)
                ->where('suggestable_type', (new Sale)->getMorphClass())
                ->where('algorithm_version', BankTransactionMatcher::ALGORITHM_VERSION)
                ->where('status', MatchSuggestionStatus::Rejected->value)
                ->pluck('suggestable_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $candidates = $result->candidates
                ->reject(fn ($candidate): bool => in_array($candidate->sale->id, $rejectedSaleIds, true))
                ->values();
            $automaticCandidate = (
                $result->automaticCandidate !== null
                && ! in_array($result->automaticCandidate->sale->id, $rejectedSaleIds, true)
            ) ? $result->automaticCandidate : null;

            BankMatchSuggestion::query()
                ->where('bank_transaction_id', $transaction->id)
                ->where('status', MatchSuggestionStatus::Pending->value)
                ->update(['status' => MatchSuggestionStatus::Expired->value]);

            foreach ($candidates as $candidate) {
                BankMatchSuggestion::query()->create([
                    'bank_transaction_id' => $transaction->id,
                    'suggestable_type' => $candidate->sale->getMorphClass(),
                    'suggestable_id' => $candidate->sale->id,
                    'score' => $candidate->score,
                    'algorithm_version' => BankTransactionMatcher::ALGORITHM_VERSION,
                    'rules' => $candidate->rules,
                    'status' => MatchSuggestionStatus::Pending,
                ]);
            }

            if (
                (bool) config('banking.sber.auto_match_enabled', true)
                && $automaticCandidate !== null
            ) {
                $candidate = $automaticCandidate;
                $allocationAmount = DecimalMoney::min(
                    (string) $transaction->amount,
                    $candidate->outstandingAmount
                );
                $allocation = $this->allocations->allocate(
                    transaction: $transaction,
                    sale: $candidate->sale,
                    amount: $allocationAmount,
                    source: AllocationSource::Automatic,
                    matchingRule: BankTransactionMatcher::ALGORITHM_VERSION,
                );
                BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $transaction->id)
                    ->where('suggestable_type', $candidate->sale->getMorphClass())
                    ->where('suggestable_id', $candidate->sale->id)
                    ->where('status', MatchSuggestionStatus::Pending->value)
                    ->update(['status' => MatchSuggestionStatus::Accepted->value]);

                if (DecimalMoney::compare((string) $transaction->amount, $allocationAmount) > 0) {
                    $transaction->forceFill([
                        'reconciliation_status' => ReconciliationStatus::Overpaid,
                        'review_reason' => 'overpayment',
                    ])->save();
                    $this->audit->record('bank.transaction.overpayment_detected', $transaction, [
                        'allocated_amount' => $allocationAmount,
                        'unallocated_amount' => $this->allocations->transactionUnallocatedAmount($transaction),
                        'sale_id' => $candidate->sale->id,
                    ]);
                    BankConnectionRequiresAttention::dispatch($transaction->connection, 'overpayment');
                }

                return $allocation->transaction->fresh();
            }

            $transaction->forceFill([
                'reconciliation_status' => $candidates->isEmpty()
                    ? ReconciliationStatus::Unmatched
                    : ReconciliationStatus::Suggested,
                'review_reason' => $candidates->count() > 1 ? 'ambiguous_match' : null,
            ])->save();

            if (
                $candidates->isEmpty()
                && DecimalMoney::compare(
                    (string) $transaction->amount,
                    (string) config('banking.unidentified_notification_amount', '100000.00')
                ) >= 0
            ) {
                BankConnectionRequiresAttention::dispatch($transaction->connection, 'large_unidentified_credit');
            }

            return $transaction->fresh();
        }, 3);
    }
}
