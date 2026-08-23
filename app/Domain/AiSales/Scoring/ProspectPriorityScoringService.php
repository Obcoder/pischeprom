<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreBand;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Enums\ScoreFactorStatus;
use App\Domain\AiSales\Enums\ScoreReviewStatus;

final class ProspectPriorityScoringService extends DeterministicScoringSupport
{
    public function __construct(private readonly ProspectPriorityDefinitionRegistry $definitions) {}

    public function calculate(ScoringInput $input, string $definitionCode = ProspectPriorityDefinitionRegistry::CODE): ScoreResult
    {
        $definition = $this->definitions->get($definitionCode);
        $this->assertSignals($input, 'prospect_priority', [
            'lane', 'role_code', 'product_scores', 'verified_public_channel', 'geography_fit', 'freshness_percent',
            'same_lane_transaction_count', 'review_completeness_percent', 'source_count',
            'unresolved_duplicate', 'stale_dossier', 'do_not_contact', 'suppressed', 'policy_blocked',
        ]);
        $this->assertSubject($input, ['unit_business_context_id', 'unit_id']);
        $this->assertLaneRole($input, $definition);
        $this->assertEvidence($input, $definition);
        $signals = $input->signals;
        $this->assertBooleanSignals($signals, [
            'verified_public_channel', 'geography_fit', 'unresolved_duplicate', 'stale_dossier',
            'do_not_contact', 'suppressed', 'policy_blocked',
        ]);
        $this->assertIntegerSignal($signals, 'freshness_percent', 0, 100);
        $this->assertIntegerSignal($signals, 'same_lane_transaction_count', 0, 1000000);
        $this->assertIntegerSignal($signals, 'review_completeness_percent', 0, 100);
        $this->assertIntegerSignal($signals, 'source_count', 0, 100);
        $productScores = $this->productScores($signals['product_scores']);
        usort($productScores, static fn (array $a, array $b): int => [-(int) $a['score'], -(int) $a['confidence'], (int) ($a['snapshot_id'] ?? PHP_INT_MAX)] <=> [-(int) $b['score'], -(int) $b['confidence'], (int) ($b['snapshot_id'] ?? PHP_INT_MAX)]);
        $top = $productScores[0] ?? ['score' => 0, 'confidence' => 0];

        $factors = [];
        $topContribution = (int) round(max(0, min(100, (int) $top['score'])) * 0.45);
        $factors[] = $this->factor(
            $input, 'top_product_relevance', 'positive', 45, $topContribution,
            $topContribution > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'score_'.max(0, min(100, (int) $top['score'])),
            $topContribution > 0 ? 'Top current Product relevance масштабирован до 45 пунктов.' : 'Current Product relevance отсутствует.',
            max(0, min(100, (int) $top['confidence'])),
        );

        $additional = array_slice(array_values(array_filter(
            array_slice($productScores, 1),
            static fn (array $row): bool => (int) $row['score'] >= 30
                && (int) $row['confidence'] >= 70
                && in_array($row['match_status'] ?? null, ['reviewed', 'approved'], true),
        )), 0, 2);
        $breadthContribution = count($additional) * 5;
        $factors[] = $this->factor(
            $input, 'additional_product_breadth', 'positive', 10, $breadthContribution,
            $breadthContribution > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'additional_'.count($additional),
            'Учтено не более двух дополнительных Product matches; top Product повторно не считался.',
            $breadthContribution > 0 ? 80 : 0,
        );

        $averageConfidence = $productScores === [] ? 0 : (int) round(array_sum(array_column($productScores, 'confidence')) / count($productScores));
        $qualityContribution = (int) round(max(0, min(100, $averageConfidence)) * 0.10);
        $factors[] = $this->factor(
            $input, 'evidence_quality_and_confidence', 'positive', 10, $qualityContribution,
            $productScores !== [] ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'confidence_'.max(0, min(100, $averageConfidence)),
            'Качество рассчитано из deterministic Product confidence, не из AI self-report.',
            $averageConfidence,
        );

        $binary = [
            'verified_public_corporate_channel' => [(bool) $signals['verified_public_channel'], 10, 'Учтено только наличие проверенного публичного корпоративного канала; значение канала не читалось.'],
            'geography_and_logistics_fit' => [(bool) $signals['geography_fit'], 5, 'Географический fit подтверждён разрешённым evidence.'],
            'same_lane_relationship_signal' => [(int) $signals['same_lane_transaction_count'] > 0, 10, 'Учтён только distinct same-lane transaction count без деталей.'],
        ];
        foreach ($binary as $code => [$applied, $weight, $rationale]) {
            $factors[] = $this->factor(
                $input, $code, 'positive', $weight, $applied ? $weight : 0,
                $applied ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
                $applied ? 'present' : 'absent', $rationale, $applied ? 100 : 0,
            );
        }

        $freshnessContribution = (int) round(max(0, min(100, (int) $signals['freshness_percent'])) * 0.05);
        $factors[] = $this->factor(
            $input, 'evidence_freshness', 'positive', 5, $freshnessContribution,
            (int) $signals['source_count'] > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'freshness_'.max(0, min(100, (int) $signals['freshness_percent'])),
            'Freshness нормализована из bounded evidence metadata.', (int) $signals['freshness_percent'],
        );
        $reviewContribution = (int) round(max(0, min(100, (int) $signals['review_completeness_percent'])) * 0.05);
        $factors[] = $this->factor(
            $input, 'review_completeness', 'positive', 5, $reviewContribution,
            $reviewContribution > 0 ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown,
            'completeness_'.max(0, min(100, (int) $signals['review_completeness_percent'])),
            'Review completeness не является outreach consent.', (int) $signals['review_completeness_percent'],
        );

        $score = $this->clamped(array_sum(array_map(static fn ($factor): int => $factor->contribution, $factors)));
        $topAtLeastThirty = (int) $top['score'] >= 30;
        foreach ([
            'unresolved_duplicate' => [(bool) $signals['unresolved_duplicate'], 40],
            'no_product_relevance_at_least_30' => [! $topAtLeastThirty, 35],
            'stale_dossier' => [(bool) $signals['stale_dossier'], 50],
        ] as $capCode => [$applies, $ceiling]) {
            if (! $applies) {
                continue;
            }
            $before = $score;
            $score = min($score, $ceiling);
            $factors[] = $this->factor(
                $input, 'cap.'.$capCode, 'cap', $ceiling, $score - $before, ScoreFactorStatus::Capped,
                'cap_'.$ceiling, 'Применён code-owned prospect cap: '.$capCode.'.', 100,
            );
        }

        $noEvidence = (int) $signals['source_count'] === 0 && $productScores === [];
        $eligibility = match (true) {
            (bool) $signals['do_not_contact'] => ScoreEligibility::BlockedDoNotContact,
            (bool) $signals['suppressed'] => ScoreEligibility::BlockedSuppressed,
            (bool) $signals['policy_blocked'] || $noEvidence => ScoreEligibility::BlockedPolicy,
            (bool) $signals['unresolved_duplicate'] => ScoreEligibility::BlockedDuplicate,
            (int) $signals['review_completeness_percent'] < 100 => ScoreEligibility::ReviewRequired,
            default => ScoreEligibility::ResearchOnly,
        };
        $review = $eligibility === ScoreEligibility::ResearchOnly ? ScoreReviewStatus::Reviewed : ScoreReviewStatus::ReviewRequired;
        $confidence = $this->confidence($factors);
        $effective = $eligibility->blocked() ? 0 : $score;
        $next = match ($eligibility) {
            ScoreEligibility::BlockedDoNotContact => 'blocked_do_not_contact',
            ScoreEligibility::BlockedDuplicate => 'review_duplicate',
            ScoreEligibility::BlockedSuppressed, ScoreEligibility::BlockedPolicy => 'enrich_public_evidence',
            ScoreEligibility::ReviewRequired => (bool) $signals['verified_public_channel'] ? 'review_product_match' : 'verify_corporate_channel',
            default => 'ready_for_outreach_compliance_review',
        };

        return new ScoreResult($definition, $input, $score, $effective, $confidence, ScoreBand::fromScore($score), $eligibility, $review, $next, $factors);
    }

    private function productScores(mixed $rows): array
    {
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 20) {
            throw new \DomainException('Product score inputs must be a bounded list.');
        }
        $allowed = ['snapshot_id', 'score', 'confidence', 'review_status', 'match_status'];
        foreach ($rows as $row) {
            if (! is_array($row) || array_diff(array_keys($row), $allowed) !== []
                || ! isset($row['score'], $row['confidence'])
                || ! is_int($row['score']) || $row['score'] < 0 || $row['score'] > 100
                || ! is_int($row['confidence']) || $row['confidence'] < 0 || $row['confidence'] > 100
                || (isset($row['snapshot_id']) && (! is_int($row['snapshot_id']) || $row['snapshot_id'] < 1))
                || (isset($row['review_status']) && ! in_array($row['review_status'], ['not_reviewed', 'review_required', 'reviewed', 'rejected'], true))
                || (isset($row['match_status']) && ! in_array($row['match_status'], ['suggested', 'reviewed', 'approved'], true))) {
                throw new \DomainException('Unknown or invalid Product score input.');
            }
        }

        return $rows;
    }
}
