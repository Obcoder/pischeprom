<?php

namespace App\Domain\Banking\Reconciliation;

use App\Domain\Banking\DTO\MatchCandidate;
use App\Domain\Banking\DTO\MatchResult;
use App\Domain\Banking\Services\DecimalMoney;
use App\Models\BankTransaction;
use App\Models\Entity;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

class BankTransactionMatcher
{
    public const ALGORITHM_VERSION = 'sale-v1';

    public function __construct(private readonly PaymentPurposeNormalizer $purpose) {}

    public function match(BankTransaction $transaction): MatchResult
    {
        $references = $this->purpose->extractReferences($transaction->purpose);
        $payerInn = $this->digits($transaction->payer_inn);
        $payerAccount = $this->digits($transaction->payer_account);
        $entityIds = ($payerInn || $payerAccount)
            ? Entity::query()
                ->where(function (Builder $query) use ($payerInn, $payerAccount): void {
                    if ($payerInn) {
                        $query->orWhere('INN', $payerInn);
                    }

                    if ($payerAccount) {
                        $query->orWhere('bank_account_number', $payerAccount);
                    }
                })
                ->pluck('id')
            : collect();
        $candidateIds = $references
            ->filter(fn (string $reference): bool => ctype_digit($reference))
            ->map(fn (string $reference): int => (int) $reference)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($references->isEmpty() && $entityIds->isEmpty()) {
            return new MatchResult(collect(), null);
        }

        $sales = Sale::query()
            ->with('entity')
            ->where(function (Builder $query) use ($references, $candidateIds, $entityIds): void {
                if ($candidateIds->isNotEmpty()) {
                    $query->orWhereIn('sales.id', $candidateIds);
                }

                if ($references->isNotEmpty()) {
                    $query->orWhereIn('payment_reference', $references->all());
                }

                if ($entityIds->isNotEmpty()) {
                    $query->orWhereIn('entity_id', $entityIds);
                }
            })
            ->whereNotIn('payment_status', ['paid', 'overpaid'])
            ->whereDate('date', '>=', $transaction->operation_date->copy()->subYear())
            ->limit(100)
            ->get();

        $candidates = $sales
            ->map(fn (Sale $sale): MatchCandidate => $this->score($transaction, $sale, $references->all()))
            ->filter(fn (MatchCandidate $candidate): bool => $candidate->score > 0)
            ->sortByDesc(fn (MatchCandidate $candidate): int => $candidate->score)
            ->values();
        $eligible = $candidates
            ->filter(fn (MatchCandidate $candidate): bool => $candidate->automaticEligible)
            ->values();

        return new MatchResult(
            candidates: $candidates,
            automaticCandidate: $eligible->count() === 1 && $candidates->count() === 1
                ? $eligible->first()
                : null,
        );
    }

    private function score(BankTransaction $transaction, Sale $sale, array $references): MatchCandidate
    {
        $score = 0;
        $rules = [];
        $saleReference = $this->purpose->normalizeReference($sale->payment_reference ?: (string) $sale->id);
        $referenceMatch = in_array($saleReference, $references, true)
            || (in_array((string) $sale->id, $references, true));

        if ($referenceMatch) {
            $score += 55;
            $rules[] = 'exact_sale_reference';
        }

        $payerInn = $this->digits($transaction->payer_inn);
        $entityInn = $this->digits($sale->entity?->INN);
        $innMatch = $payerInn !== null && $entityInn !== null && hash_equals($entityInn, $payerInn);

        if ($innMatch) {
            $score += 25;
            $rules[] = 'payer_inn';
        }

        $payerAccount = $this->digits($transaction->payer_account);
        $entityAccount = $this->digits($sale->entity?->bank_account_number);
        $accountMatch = $payerAccount !== null
            && $entityAccount !== null
            && hash_equals($entityAccount, $payerAccount);

        if ($accountMatch) {
            $score += 20;
            $rules[] = 'payer_account';
        }

        $outstanding = DecimalMoney::max(
            DecimalMoney::subtract((string) $sale->total, (string) $sale->paid_amount),
            '0.00'
        );
        $amountComparison = DecimalMoney::compare((string) $transaction->amount, $outstanding);
        $amountCompatible = DecimalMoney::isPositive($outstanding);

        if ($amountCompatible && $amountComparison === 0) {
            $score += 15;
            $rules[] = 'exact_outstanding_amount';
        } elseif ($amountCompatible && $amountComparison < 0) {
            $score += 10;
            $rules[] = 'partial_payment_amount';
        } elseif ($amountCompatible) {
            $score += 5;
            $rules[] = 'overpayment_amount';
        }

        if ($sale->date && $sale->date->diffInDays($transaction->operation_date, true) <= 90) {
            $score += 5;
            $rules[] = 'date_proximity';
        }

        $threshold = min(100, max(0, (int) config('banking.sber.auto_match_threshold', 90)));
        $identityMatch = $innMatch || $accountMatch;
        $automaticEligible = $referenceMatch
            && $identityMatch
            && $amountCompatible
            && $score >= $threshold;

        return new MatchCandidate(
            sale: $sale,
            score: min(100, $score),
            rules: $rules,
            automaticEligible: $automaticEligible,
            outstandingAmount: $outstanding,
        );
    }

    private function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/u', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
