<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Scoring\GoodFitScoringService;
use App\Domain\AiSales\Scoring\ProductRelevanceScoringService;
use App\Domain\AiSales\Scoring\ProspectPriorityScoringService;
use App\Domain\AiSales\Scoring\ScoringInput;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScoreSyntheticProspectCommand extends Command
{
    protected $signature = 'ai-sales:score-synthetic-prospect
        {scenario=all : A repository-owned scenario name or all}';

    protected $description = 'Calculate deterministic Stage 10 scores for repository-owned synthetic scenarios without HTTP or persistence';

    public function handle(
        ProductRelevanceScoringService $products,
        GoodFitScoringService $goods,
        ProspectPriorityScoringService $priority,
    ): int {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('Blocked: synthetic scoring is allowed only in local, testing, or staging.');

            return self::FAILURE;
        }
        Http::preventStrayRequests();
        $available = [
            'high_product_relevance', 'directory_only_cap', 'contradictory_evidence',
            'good_mapping_0_1_n', 'high_product_low_good_fit', 'multiple_products_priority',
            'do_not_contact_block', 'dual_lane_isolation', 'manual_override_expiry', 'stale_snapshot',
        ];
        $selected = $this->argument('scenario') === 'all' ? $available : [(string) $this->argument('scenario')];
        if (array_diff($selected, $available) !== []) {
            $this->error('Unknown synthetic scoring scenario.');

            return self::INVALID;
        }

        $rows = [];
        foreach ($selected as $scenario) {
            [$level, $result, $note] = $this->runScenario($scenario, $products, $goods, $priority);
            $rows[] = [$scenario, $level, $result?->computedScore ?? '-', $result?->effectiveScore ?? '-', $result?->eligibility->value ?? '-', $note];
        }
        $this->table(['scenario', 'level', 'computed', 'effective', 'eligibility', 'result'], $rows);
        $this->comment('Synthetic fixture only; persistence=0, HTTP=0, email=0, Unit/Entity mutations=0.');

        return self::SUCCESS;
    }

    private function runScenario(string $scenario, ProductRelevanceScoringService $products, GoodFitScoringService $goods, ProspectPriorityScoringService $priority): array
    {
        if (in_array($scenario, ['high_product_relevance', 'directory_only_cap', 'contradictory_evidence', 'dual_lane_isolation'], true)) {
            $signals = $this->productSignals();
            if ($scenario === 'directory_only_cap') {
                $signals['directory_only'] = true;
                $signals['has_primary_corporate_source'] = false;
            }
            if ($scenario === 'contradictory_evidence') {
                $signals['contradiction_count'] = 1;
            }
            if ($scenario === 'dual_lane_isolation') {
                $signals['lane'] = 'procurement';
                $signals['role_code'] = 'prospective_supplier';
                $signals['same_lane_transaction_count'] = 1;
            }
            $input = new ScoringInput('product_relevance', ['unit_product_match_id' => 1, 'unit_id' => 1, 'unit_business_context_id' => $signals['lane'] === 'sales' ? 1 : 2, 'product_id' => 1], $signals, $this->syntheticEvidence());

            return ['product', $products->calculate($input), 'deterministic'];
        }
        if (in_array($scenario, ['good_mapping_0_1_n', 'high_product_low_good_fit'], true)) {
            $signals = $this->goodSignals();
            if ($scenario === 'good_mapping_0_1_n') {
                $signals['mapping_state'] = 'ambiguous_product_mapping';
                $signals['product_id_matches'] = false;
            }
            $input = new ScoringInput('good_fit', ['unit_good_match_id' => 1, 'unit_product_match_id' => 1, 'unit_id' => 1, 'unit_business_context_id' => 1, 'good_id' => 1, 'product_id' => 1], $signals, [[
                'factor_code' => 'exact_product_mapping', 'type' => 'repository_fixture',
                'reference' => 'fixture:stage10:good-product-mapping',
                'hash' => hash('sha256', 'fixture:stage10:good-product-mapping'),
                'confidence' => 100, 'verified' => true, 'at' => null,
            ]]);

            return ['good', $goods->calculate($input), $scenario === 'good_mapping_0_1_n' ? '0/N mapping blocks score' : 'unknown commercial fields remain zero'];
        }
        if (in_array($scenario, ['multiple_products_priority', 'do_not_contact_block'], true)) {
            $signals = $this->prioritySignals();
            if ($scenario === 'do_not_contact_block') {
                $signals['do_not_contact'] = true;
            }
            $input = new ScoringInput('prospect_priority', ['unit_business_context_id' => 1, 'unit_id' => 1], $signals, []);

            return ['priority', $priority->calculate($input), 'computed score remains transparent'];
        }

        return ['lifecycle', null, $scenario === 'manual_override_expiry' ? 'expiry requires append-only recalculation' : 'stale metadata requires recalculation'];
    }

    private function productSignals(): array
    {
        return [
            'lane' => 'sales', 'direct_product_mention' => true, 'process_or_end_product_use' => true,
            'role_code' => 'prospective_customer',
            'industry_activity_fit' => true, 'verified_public_product_evidence' => true,
            'independent_source_count' => 2, 'same_lane_transaction_count' => 1,
            'geographic_serviceability' => true, 'contradiction_count' => 0, 'stale_evidence_count' => 0,
            'directory_only' => false, 'has_primary_corporate_source' => true,
            'unresolved_duplicate' => false, 'rejected' => false, 'policy_blocked' => false,
        ];
    }

    private function goodSignals(): array
    {
        return [
            'lane' => 'sales', 'mapping_state' => 'mapped', 'product_match_active' => true,
            'role_code' => 'prospective_customer',
            'good_match_active' => true,
            'product_id_matches' => true, 'good_published' => true,
            'format_or_processing_fit' => null, 'packaging_or_moq_fit' => null,
            'origin_grade_size_fit' => null, 'regional_delivery_or_supply_fit' => null,
            'approved_availability_signal' => null, 'approved_price_fit' => null,
            'stale_commercial' => false, 'missing_essential_offer_data' => true, 'policy_blocked' => false,
        ];
    }

    private function prioritySignals(): array
    {
        return [
            'lane' => 'sales', 'role_code' => 'prospective_customer', 'product_scores' => [['score' => 90, 'confidence' => 90, 'match_status' => 'reviewed'], ['score' => 70, 'confidence' => 80, 'match_status' => 'reviewed'], ['score' => 50, 'confidence' => 70, 'match_status' => 'approved']],
            'verified_public_channel' => true, 'geography_fit' => true, 'freshness_percent' => 100,
            'same_lane_transaction_count' => 1, 'review_completeness_percent' => 100, 'source_count' => 3,
            'unresolved_duplicate' => false, 'stale_dossier' => false, 'do_not_contact' => false,
            'suppressed' => false, 'policy_blocked' => false,
        ];
    }

    private function syntheticEvidence(): array
    {
        return array_map(static fn (string $factor): array => [
            'factor_code' => $factor, 'type' => 'repository_fixture', 'reference' => 'fixture:stage10:'.$factor,
            'hash' => hash('sha256', 'fixture:stage10:'.$factor), 'confidence' => 90, 'verified' => true, 'at' => '2026-08-17T00:00:00+03:00',
        ], ['direct_product_mention', 'process_or_end_product_use', 'industry_activity_fit', 'verified_public_product_evidence', 'geographic_serviceability']);
    }
}
