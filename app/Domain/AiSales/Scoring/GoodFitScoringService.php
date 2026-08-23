<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreBand;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Enums\ScoreFactorStatus;
use App\Domain\AiSales\Enums\ScoreReviewStatus;

final class GoodFitScoringService extends DeterministicScoringSupport
{
    public function __construct(private readonly GoodFitDefinitionRegistry $definitions) {}

    public function calculate(ScoringInput $input, string $definitionCode = GoodFitDefinitionRegistry::CODE): ScoreResult
    {
        $definition = $this->definitions->get($definitionCode);
        $this->assertSignals($input, 'good_fit', [
            'lane', 'role_code', 'mapping_state', 'product_match_active', 'good_match_active', 'product_id_matches', 'good_published',
            'format_or_processing_fit', 'packaging_or_moq_fit', 'origin_grade_size_fit',
            'regional_delivery_or_supply_fit', 'approved_availability_signal', 'approved_price_fit',
            'stale_commercial', 'missing_essential_offer_data', 'policy_blocked',
        ]);
        $this->assertSubject($input, ['unit_good_match_id', 'unit_product_match_id', 'unit_id', 'unit_business_context_id', 'good_id', 'product_id']);
        $this->assertLaneRole($input, $definition);
        $this->assertEvidence($input, $definition);
        $signals = $input->signals;
        $mappingState = (string) $signals['mapping_state'];
        if (! in_array($mappingState, ['mapped', 'legacy_unreconciled', 'missing_product_mapping', 'ambiguous_product_mapping', 'product_scope_mismatch', 'not_applicable'], true)) {
            throw new \DomainException('Unknown Good Product mapping state.');
        }
        $this->assertBooleanSignals($signals, [
            'product_match_active', 'good_match_active', 'product_id_matches', 'good_published',
            'stale_commercial', 'missing_essential_offer_data', 'policy_blocked',
        ]);
        foreach (['format_or_processing_fit', 'packaging_or_moq_fit', 'origin_grade_size_fit', 'regional_delivery_or_supply_fit', 'approved_availability_signal', 'approved_price_fit'] as $key) {
            if ($signals[$key] !== null && ! is_bool($signals[$key])) {
                throw new \DomainException('Good-fit commercial signals must be boolean or unknown.');
            }
        }
        $mappingExact = $mappingState === 'mapped' && (bool) $signals['product_id_matches'];
        $mappingUnavailable = in_array($mappingState, ['missing_product_mapping', 'ambiguous_product_mapping', 'legacy_unreconciled', 'not_applicable'], true);
        $mappingBlocked = $mappingState === 'product_scope_mismatch' || ($mappingState === 'mapped' && ! (bool) $signals['product_id_matches']);
        $policyBlocked = (bool) $signals['policy_blocked'] || $mappingBlocked
            || ! (bool) $signals['product_match_active'] || ! (bool) $signals['good_match_active'];

        $factors = [];
        $score = 0;
        $mappingContribution = $mappingExact ? 20 : 0;
        $score += $mappingContribution;
        $factors[] = $this->factor(
            $input, 'exact_product_mapping', $mappingBlocked ? 'block' : 'positive', 20, $mappingContribution,
            $mappingBlocked ? ScoreFactorStatus::Blocked : ($mappingExact ? ScoreFactorStatus::Applied : ScoreFactorStatus::Unknown),
            $mappingState,
            $mappingExact ? 'Good имеет одну distinct Product mapping, совпадающую с UnitProductMatch.' : 'Exact Product mapping не подтверждён.',
            $mappingExact ? 100 : 0,
        );

        $commercialKnown = 0;
        $commercialPermitted = $mappingExact && ! $policyBlocked;
        foreach ([
            'format_or_processing_fit' => 20,
            'packaging_or_moq_fit' => 15,
            'origin_grade_size_fit' => 15,
            'regional_delivery_or_supply_fit' => 10,
            'approved_availability_signal' => 10,
            'approved_price_fit' => 10,
        ] as $code => $weight) {
            $value = $signals[$code];
            $known = is_bool($value);
            $matched = $commercialPermitted && $value === true;
            $contribution = $matched ? $weight : 0;
            $score += $contribution;
            $commercialKnown += $known ? 1 : 0;
            $factors[] = $this->factor(
                $input, $code, 'positive', $weight, $contribution,
                ! $commercialPermitted ? ScoreFactorStatus::Blocked : ($matched ? ScoreFactorStatus::Applied : ($known ? ScoreFactorStatus::Rejected : ScoreFactorStatus::Unknown)),
                ! $commercialPermitted ? 'prerequisite_blocked' : ($matched ? 'matched' : ($known ? 'not_matched' : 'unknown')),
                ! $commercialPermitted ? 'Good-fit prerequisite не выполнен; коммерческий фактор не учитывается.' : ($matched ? 'Проверенный offer attribute соответствует Product need.' : ($known ? 'Проверенный attribute не соответствует.' : 'Audited подтверждённый attribute отсутствует; вклад равен 0.')),
            );
        }

        $score = $this->clamped($score);
        foreach ([
            'inactive_unpublished_good' => [! (bool) $signals['good_published'], 20],
            'stale_price_or_availability' => [(bool) $signals['stale_commercial'], 50],
            'missing_essential_offer_data' => [(bool) $signals['missing_essential_offer_data'], 60],
            'no_commercial_fields_beyond_product' => [$commercialKnown === 0, 45],
        ] as $capCode => [$applies, $ceiling]) {
            if (! $applies) {
                continue;
            }
            $before = $score;
            $score = min($score, $ceiling);
            $factors[] = $this->factor(
                $input, 'cap.'.$capCode, 'cap', $ceiling, $score - $before, ScoreFactorStatus::Capped,
                'cap_'.$ceiling, 'Применён code-owned Good-fit cap: '.$capCode.'.', 100,
            );
        }

        if ($mappingUnavailable) {
            $score = 0;
        }
        if ($policyBlocked) {
            $score = 0;
        }

        $eligibility = match (true) {
            $policyBlocked => ScoreEligibility::BlockedPolicy,
            $mappingUnavailable => ScoreEligibility::ReviewRequired,
            default => ScoreEligibility::ResearchOnly,
        };
        $review = $eligibility === ScoreEligibility::ResearchOnly
            ? ScoreReviewStatus::NotReviewed
            : ScoreReviewStatus::ReviewRequired;
        $confidence = $this->confidence($factors);
        $effective = $eligibility->blocked() ? 0 : $score;
        $next = match (true) {
            $mappingUnavailable || $mappingBlocked => 'review_good_offer_fit',
            $commercialKnown === 0 => 'review_good_offer_fit',
            default => 'ready_for_outreach_compliance_review',
        };

        return new ScoreResult($definition, $input, $score, $effective, $confidence, ScoreBand::fromScore($score), $eligibility, $review, $next, $factors);
    }
}
