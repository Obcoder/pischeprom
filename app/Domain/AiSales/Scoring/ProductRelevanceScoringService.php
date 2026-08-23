<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreBand;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Enums\ScoreFactorStatus;
use App\Domain\AiSales\Enums\ScoreReviewStatus;

final class ProductRelevanceScoringService extends DeterministicScoringSupport
{
    public function __construct(private readonly ProductRelevanceDefinitionRegistry $definitions) {}

    public function calculate(ScoringInput $input, string $definitionCode = ProductRelevanceDefinitionRegistry::CODE): ScoreResult
    {
        $definition = $this->definitions->get($definitionCode);
        $this->assertSignals($input, 'product_relevance', [
            'lane', 'role_code', 'direct_product_mention', 'process_or_end_product_use', 'industry_activity_fit',
            'verified_public_product_evidence', 'independent_source_count', 'same_lane_transaction_count',
            'geographic_serviceability', 'contradiction_count', 'stale_evidence_count', 'directory_only',
            'has_primary_corporate_source', 'unresolved_duplicate', 'rejected', 'policy_blocked',
        ]);
        $this->assertSubject($input, ['unit_product_match_id', 'unit_id', 'unit_business_context_id', 'product_id']);
        $this->assertLaneRole($input, $definition);
        $this->assertEvidence($input, $definition);
        $this->assertBooleanSignals($input->signals, [
            'direct_product_mention', 'process_or_end_product_use', 'industry_activity_fit',
            'verified_public_product_evidence', 'geographic_serviceability', 'directory_only',
            'has_primary_corporate_source', 'unresolved_duplicate', 'rejected', 'policy_blocked',
        ]);
        $this->assertIntegerSignal($input->signals, 'independent_source_count', 0, 20);
        $this->assertIntegerSignal($input->signals, 'same_lane_transaction_count', 0, 1000000);
        $this->assertIntegerSignal($input->signals, 'contradiction_count', 0, 50);
        $this->assertIntegerSignal($input->signals, 'stale_evidence_count', 0, 50);
        $this->assertRequiredEvidence($input, [
            'direct_product_mention' => 'direct_product_mention',
            'process_or_end_product_use' => 'process_or_end_product_use',
            'industry_activity_fit' => 'industry_activity_fit',
            'verified_public_product_evidence' => 'verified_public_product_evidence',
            'geographic_serviceability' => 'geographic_serviceability',
        ]);
        if ((int) $input->signals['independent_source_count'] >= 2
            && count(array_unique(array_column($input->evidence, 'reference'))) < 2) {
            throw new \DomainException('Independent corroboration requires two evidence references.');
        }

        $signals = $input->signals;
        $factors = [];
        $score = 0;
        foreach ([
            'direct_product_mention' => 25,
            'process_or_end_product_use' => 20,
            'industry_activity_fit' => 15,
            'verified_public_product_evidence' => 15,
            'geographic_serviceability' => 5,
        ] as $code => $weight) {
            $applied = (bool) $signals[$code];
            $contribution = $applied ? $weight : 0;
            $score += $contribution;
            $factors[] = $this->factor(
                $input, $code, 'positive', $weight, $contribution,
                $applied ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
                $applied ? 'verified_signal' : 'not_verified',
                $applied ? 'Фактор подтверждён разрешённой ссылкой на evidence.' : 'Проверенное evidence для фактора отсутствует.',
            );
        }

        $corroborated = (int) $signals['independent_source_count'] >= 2;
        $corroboration = $corroborated ? 10 : 0;
        $score += $corroboration;
        $factors[] = $this->factor(
            $input, 'independent_source_corroboration', 'positive', 10, $corroboration,
            $corroborated ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'source_families_'.min(9, (int) $signals['independent_source_count']),
            $corroborated ? 'Факт подтверждён независимыми source families.' : 'Независимая коррoборация не подтверждена.',
            $corroborated ? 80 : 0,
        );

        $hasTransactions = (int) $signals['same_lane_transaction_count'] > 0;
        $transaction = $hasTransactions ? 10 : 0;
        $score += $transaction;
        $factors[] = $this->factor(
            $input, 'historical_same_lane_transaction', 'positive', 10, $transaction,
            $hasTransactions ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            $hasTransactions ? 'present' : 'absent',
            $hasTransactions ? 'Есть distinct транзакция через Entity в том же lane; детали не копировались.' : 'Same-lane транзакционный сигнал отсутствует.',
            $hasTransactions ? 100 : 0,
        );

        $contradiction = min(20, max(0, (int) $signals['contradiction_count']) * 20);
        $score -= $contradiction;
        $factors[] = $this->factor(
            $input, 'contradictory_evidence', 'negative', 20, -$contradiction,
            $contradiction > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            $contradiction > 0 ? 'present' : 'absent',
            $contradiction > 0 ? 'Сосуществующее contradicted evidence применило penalty.' : 'Противоречие не обнаружено.',
            $contradiction > 0 ? 100 : 0,
        );

        $stale = min(10, max(0, (int) $signals['stale_evidence_count']) * 10);
        $score -= $stale;
        $factors[] = $this->factor(
            $input, 'stale_evidence', 'negative', 10, -$stale,
            $stale > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            $stale > 0 ? 'present' : 'absent',
            $stale > 0 ? 'Stale evidence применило penalty.' : 'Stale evidence не обнаружено.',
            $stale > 0 ? 100 : 0,
        );

        $score = $this->clamped($score);
        foreach ([
            'directory_only_evidence' => [(bool) $signals['directory_only'], 45],
            'no_primary_corporate_source' => [! (bool) $signals['has_primary_corporate_source'], 55],
            'unresolved_duplicate' => [(bool) $signals['unresolved_duplicate'], 40],
            'rejected_product_match' => [(bool) $signals['rejected'], 0],
        ] as $capCode => [$applies, $ceiling]) {
            if (! $applies) {
                continue;
            }
            $before = $score;
            $score = min($score, $ceiling);
            $factors[] = $this->factor(
                $input, 'cap.'.$capCode, 'cap', $ceiling, $score - $before, ScoreFactorStatus::Capped,
                'cap_'.$ceiling, 'Применён code-owned cap: '.$capCode.'.', 100,
            );
        }

        $hasEvidence = (bool) ($signals['direct_product_mention'] || $signals['process_or_end_product_use']
            || $signals['industry_activity_fit'] || $signals['verified_public_product_evidence']
            || $signals['same_lane_transaction_count'] || $signals['geographic_serviceability']);
        $eligibility = match (true) {
            (bool) $signals['policy_blocked'] => ScoreEligibility::BlockedPolicy,
            ! $hasEvidence, (bool) $signals['unresolved_duplicate'] => ScoreEligibility::ReviewRequired,
            default => ScoreEligibility::ResearchOnly,
        };
        $review = match (true) {
            (bool) $signals['rejected'] => ScoreReviewStatus::Rejected,
            $eligibility !== ScoreEligibility::ResearchOnly => ScoreReviewStatus::ReviewRequired,
            default => ScoreReviewStatus::NotReviewed,
        };
        $confidence = $this->confidence($factors, $contradiction > 0 ? 20 : 0);
        $effective = $eligibility->blocked() ? 0 : $score;
        $next = match (true) {
            (bool) $signals['unresolved_duplicate'] => 'review_duplicate',
            ! $hasEvidence => 'enrich_public_evidence',
            $stale > 0 => 'refresh_stale_evidence',
            $review !== ScoreReviewStatus::Reviewed => 'review_product_match',
            default => 'ready_for_outreach_compliance_review',
        };

        return new ScoreResult($definition, $input, $score, $effective, $confidence, ScoreBand::fromScore($score), $eligibility, $review, $next, $factors);
    }
}
