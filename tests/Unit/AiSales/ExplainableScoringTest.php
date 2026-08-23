<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Enums\ScoreBand;
use App\Domain\AiSales\Enums\ScoreConfidenceBand;
use App\Domain\AiSales\Enums\ScoreEligibility;
use App\Domain\AiSales\Scoring\GoodFitDefinitionRegistry;
use App\Domain\AiSales\Scoring\GoodFitScoringService;
use App\Domain\AiSales\Scoring\ProductRelevanceDefinitionRegistry;
use App\Domain\AiSales\Scoring\ProductRelevanceScoringService;
use App\Domain\AiSales\Scoring\ProspectPriorityDefinitionRegistry;
use App\Domain\AiSales\Scoring\ProspectPriorityScoringService;
use App\Domain\AiSales\Scoring\ScoringInput;
use DomainException;
use Tests\TestCase;

class ExplainableScoringTest extends TestCase
{
    public function test_definition_hashes_are_stable_and_unknown_definitions_fail_closed(): void
    {
        $this->assertSame('7621c4672da16df6ad567771362a1961b1a50f3e935a711cf1425b12cdf85076', app(ProductRelevanceDefinitionRegistry::class)->get()->hash);
        $this->assertSame('11a93072b6456587b4ea9508fdf9a97af72a71e5432d41d832dcd2924e1c26e9', app(GoodFitDefinitionRegistry::class)->get()->hash);
        $this->assertSame('e9222e350fb3ef1722a64a9d8f3f475b25e8f4dceea0d47029d9ed19ff1c8984', app(ProspectPriorityDefinitionRegistry::class)->get()->hash);

        try {
            app(ProductRelevanceDefinitionRegistry::class)->get(ProductRelevanceDefinitionRegistry::CODE, '9.9.9');
            $this->fail('Unknown scoring definition version was accepted.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $this->expectException(DomainException::class);
        app(ProductRelevanceDefinitionRegistry::class)->get('product_relevance.browser_edit');
    }

    public function test_score_and_confidence_band_boundaries_are_exact(): void
    {
        $this->assertSame(ScoreBand::Low, ScoreBand::fromScore(24));
        $this->assertSame(ScoreBand::Medium, ScoreBand::fromScore(25));
        $this->assertSame(ScoreBand::Medium, ScoreBand::fromScore(49));
        $this->assertSame(ScoreBand::Promising, ScoreBand::fromScore(50));
        $this->assertSame(ScoreBand::Promising, ScoreBand::fromScore(69));
        $this->assertSame(ScoreBand::High, ScoreBand::fromScore(70));
        $this->assertSame(ScoreBand::High, ScoreBand::fromScore(84));
        $this->assertSame(ScoreBand::VeryHigh, ScoreBand::fromScore(85));
        $this->assertSame(ScoreBand::VeryHigh, ScoreBand::fromScore(100));
        $this->assertSame(ScoreConfidenceBand::Low, ScoreConfidenceBand::fromConfidence(39));
        $this->assertSame(ScoreConfidenceBand::Medium, ScoreConfidenceBand::fromConfidence(40));
        $this->assertSame(ScoreConfidenceBand::Medium, ScoreConfidenceBand::fromConfidence(69));
        $this->assertSame(ScoreConfidenceBand::High, ScoreConfidenceBand::fromConfidence(70));
    }

    public function test_product_contributions_penalties_caps_and_hashes_are_deterministic(): void
    {
        $service = app(ProductRelevanceScoringService::class);
        $input = $this->productInput();
        $first = $service->calculate($input);
        $second = $service->calculate($this->productInput());
        $this->assertSame(100, $first->computedScore);
        $this->assertSame($first->safeArray(), $second->safeArray());
        $this->assertSame($input->inputHash, $second->input->inputHash);
        $this->assertSame(100, array_sum(array_map(fn ($factor) => $factor->contribution, array_filter($first->factors, fn ($factor) => $factor->polarity === 'positive'))));

        $directory = $this->productSignals();
        $directory['directory_only'] = true;
        $directory['has_primary_corporate_source'] = false;
        $result = $service->calculate($this->productInput($directory));
        $this->assertSame(45, $result->computedScore);
        $this->assertContains('cap.directory_only_evidence', array_column($result->safeArray()['factors'], 'factor_code'));

        $noPrimary = $this->productSignals();
        $noPrimary['has_primary_corporate_source'] = false;
        $this->assertSame(55, $service->calculate($this->productInput($noPrimary))->computedScore);

        $contradicted = $this->productSignals();
        $contradicted['contradiction_count'] = 1;
        $contradicted['stale_evidence_count'] = 1;
        $this->assertSame(70, $service->calculate($this->productInput($contradicted))->computedScore);
    }

    public function test_no_evidence_and_unknown_signal_fail_closed(): void
    {
        $signals = $this->productSignals();
        foreach (['direct_product_mention', 'process_or_end_product_use', 'industry_activity_fit', 'verified_public_product_evidence', 'geographic_serviceability'] as $key) {
            $signals[$key] = false;
        }
        $signals['independent_source_count'] = 0;
        $signals['same_lane_transaction_count'] = 0;
        $result = app(ProductRelevanceScoringService::class)->calculate($this->productInput($signals, []));
        $this->assertSame(0, $result->computedScore);
        $this->assertSame(ScoreEligibility::ReviewRequired, $result->eligibility);

        $signals['browser_weight'] = 100;
        try {
            app(ProductRelevanceScoringService::class)->calculate($this->productInput($signals));
            $this->fail('Unknown scoring signal was accepted.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $unknownEvidence = $this->productInput();
        $unknownEvidence = new ScoringInput($unknownEvidence->level, $unknownEvidence->subject, $unknownEvidence->signals, [[
            ...$unknownEvidence->evidence[0], 'factor_code' => 'browser_defined_factor',
        ], ...array_slice($unknownEvidence->evidence, 1)]);
        $this->expectException(DomainException::class);
        app(ProductRelevanceScoringService::class)->calculate($unknownEvidence);
    }

    public function test_good_fit_uses_distinct_mapping_and_unknown_fields_contribute_zero(): void
    {
        $service = app(GoodFitScoringService::class);
        $result = $service->calculate($this->goodInput());
        $this->assertSame(20, $result->computedScore);
        $this->assertSame(0, collect($result->factors)->firstWhere('code', 'packaging_or_moq_fit')->contribution);
        $this->assertSame('unknown', collect($result->factors)->firstWhere('code', 'approved_availability_signal')->normalizedState);

        $signals = $this->goodSignals();
        foreach (['format_or_processing_fit', 'packaging_or_moq_fit', 'origin_grade_size_fit', 'regional_delivery_or_supply_fit', 'approved_availability_signal', 'approved_price_fit'] as $key) {
            $signals[$key] = true;
        }
        $signals['missing_essential_offer_data'] = false;
        $this->assertSame(100, $service->calculate($this->goodInput($signals))->computedScore);

        $ambiguous = $this->goodSignals();
        $ambiguous['mapping_state'] = 'ambiguous_product_mapping';
        $ambiguous['product_id_matches'] = false;
        $blocked = $service->calculate($this->goodInput($ambiguous));
        $this->assertSame(0, $blocked->computedScore);
        $this->assertSame(ScoreEligibility::ReviewRequired, $blocked->eligibility);

        $mismatch = $this->goodSignals();
        $mismatch['mapping_state'] = 'product_scope_mismatch';
        $mismatch['product_id_matches'] = false;
        $mismatch['format_or_processing_fit'] = true;
        $mismatchResult = $service->calculate($this->goodInput($mismatch));
        $this->assertSame(0, $mismatchResult->computedScore);
        $this->assertSame(ScoreEligibility::BlockedPolicy, $mismatchResult->eligibility);
        $this->assertSame(0, collect($mismatchResult->factors)->firstWhere('code', 'format_or_processing_fit')->contribution);

        $unpublished = $signals;
        $unpublished['good_published'] = false;
        $this->assertSame(20, $service->calculate($this->goodInput($unpublished))->computedScore);
        $stale = $signals;
        $stale['stale_commercial'] = true;
        $this->assertSame(50, $service->calculate($this->goodInput($stale))->computedScore);

        $inactive = $this->goodSignals();
        $inactive['product_match_active'] = false;
        $this->assertSame(ScoreEligibility::BlockedPolicy, $service->calculate($this->goodInput($inactive))->eligibility);
    }

    public function test_priority_breadth_is_capped_and_dnc_does_not_falsify_computed_score(): void
    {
        $service = app(ProspectPriorityScoringService::class);
        $normal = $service->calculate($this->priorityInput());
        $signals = $this->prioritySignals();
        $signals['do_not_contact'] = true;
        $dnc = $service->calculate($this->priorityInput($signals));
        $this->assertSame($normal->computedScore, $dnc->computedScore);
        $this->assertSame(0, $dnc->effectiveScore);
        $this->assertSame(ScoreEligibility::BlockedDoNotContact, $dnc->eligibility);
        $this->assertSame(10, collect($normal->factors)->firstWhere('code', 'additional_product_breadth')->contribution);
        $this->assertSame(10, collect($normal->factors)->firstWhere('code', 'verified_public_corporate_channel')->contribution);
        $this->assertSame(5, collect($normal->factors)->firstWhere('code', 'geography_and_logistics_fit')->contribution);
        $this->assertSame(5, collect($normal->factors)->firstWhere('code', 'evidence_freshness')->contribution);

        $signals['do_not_contact'] = false;
        $signals['suppressed'] = true;
        $suppressed = $service->calculate($this->priorityInput($signals));
        $this->assertSame($normal->computedScore, $suppressed->computedScore);
        $this->assertSame(0, $suppressed->effectiveScore);
        $this->assertSame(ScoreEligibility::BlockedSuppressed, $suppressed->eligibility);

        $signals = $this->prioritySignals();
        $signals['product_scores'] = [['score' => 29, 'confidence' => 100]];
        $capped = $service->calculate($this->priorityInput($signals));
        $this->assertLessThanOrEqual(35, $capped->computedScore);

        $duplicate = $this->prioritySignals();
        $duplicate['unresolved_duplicate'] = true;
        $duplicateResult = $service->calculate($this->priorityInput($duplicate));
        $this->assertLessThanOrEqual(40, $duplicateResult->computedScore);
        $this->assertSame(0, $duplicateResult->effectiveScore);
        $this->assertSame(ScoreEligibility::BlockedDuplicate, $duplicateResult->eligibility);

        $unreviewedBreadth = $this->prioritySignals();
        $unreviewedBreadth['product_scores'][1]['match_status'] = 'suggested';
        $unreviewedBreadth['product_scores'][2]['confidence'] = 69;
        $unreviewedBreadth['product_scores'][3]['match_status'] = 'suggested';
        $this->assertSame(0, collect($service->calculate($this->priorityInput($unreviewedBreadth))->factors)
            ->firstWhere('code', 'additional_product_breadth')->contribution);
    }

    private function productInput(?array $signals = null, ?array $evidence = null): ScoringInput
    {
        return new ScoringInput(
            'product_relevance',
            ['unit_product_match_id' => 1, 'unit_id' => 1, 'unit_business_context_id' => 1, 'product_id' => 1],
            $signals ?? $this->productSignals(),
            $evidence ?? array_map(fn (string $factor): array => $this->evidence($factor), [
                'direct_product_mention', 'process_or_end_product_use', 'industry_activity_fit',
                'verified_public_product_evidence', 'geographic_serviceability',
            ]),
        );
    }

    private function productSignals(): array
    {
        return [
            'lane' => 'sales', 'role_code' => 'prospective_customer', 'direct_product_mention' => true, 'process_or_end_product_use' => true,
            'industry_activity_fit' => true, 'verified_public_product_evidence' => true,
            'independent_source_count' => 2, 'same_lane_transaction_count' => 1,
            'geographic_serviceability' => true, 'contradiction_count' => 0, 'stale_evidence_count' => 0,
            'directory_only' => false, 'has_primary_corporate_source' => true,
            'unresolved_duplicate' => false, 'rejected' => false, 'policy_blocked' => false,
        ];
    }

    private function goodInput(?array $signals = null): ScoringInput
    {
        return new ScoringInput('good_fit', [
            'unit_good_match_id' => 1, 'unit_product_match_id' => 1, 'unit_id' => 1,
            'unit_business_context_id' => 1, 'good_id' => 1, 'product_id' => 1,
        ], $signals ?? $this->goodSignals(), [$this->evidence('exact_product_mapping')]);
    }

    private function goodSignals(): array
    {
        return [
            'lane' => 'sales', 'role_code' => 'prospective_customer', 'mapping_state' => 'mapped', 'product_match_active' => true,
            'good_match_active' => true,
            'product_id_matches' => true, 'good_published' => true,
            'format_or_processing_fit' => null, 'packaging_or_moq_fit' => null,
            'origin_grade_size_fit' => null, 'regional_delivery_or_supply_fit' => null,
            'approved_availability_signal' => null, 'approved_price_fit' => null,
            'stale_commercial' => false, 'missing_essential_offer_data' => true, 'policy_blocked' => false,
        ];
    }

    private function priorityInput(?array $signals = null): ScoringInput
    {
        return new ScoringInput('prospect_priority', ['unit_business_context_id' => 1, 'unit_id' => 1], $signals ?? $this->prioritySignals(), []);
    }

    private function prioritySignals(): array
    {
        return [
            'lane' => 'sales', 'role_code' => 'prospective_customer',
            'product_scores' => [
                ['score' => 90, 'confidence' => 90, 'match_status' => 'reviewed'],
                ['score' => 70, 'confidence' => 80, 'match_status' => 'reviewed'],
                ['score' => 50, 'confidence' => 70, 'match_status' => 'approved'],
                ['score' => 40, 'confidence' => 60, 'match_status' => 'reviewed'],
            ],
            'verified_public_channel' => true, 'geography_fit' => true, 'freshness_percent' => 100,
            'same_lane_transaction_count' => 1, 'review_completeness_percent' => 100, 'source_count' => 3,
            'unresolved_duplicate' => false, 'stale_dossier' => false, 'do_not_contact' => false,
            'suppressed' => false, 'policy_blocked' => false,
        ];
    }

    private function evidence(string $factor): array
    {
        return [
            'factor_code' => $factor, 'type' => 'repository_fixture', 'reference' => 'fixture:'.$factor,
            'hash' => hash('sha256', 'fixture:'.$factor), 'confidence' => 90, 'verified' => true,
            'at' => '2026-08-17T00:00:00+03:00',
        ];
    }
}
