<?php

namespace App\Domain\AiSales\Scoring;

use DomainException;

final class ProspectPriorityDefinitionRegistry
{
    public const CODE = 'prospect_priority.v1';

    public const VERSION = '1.0.0';

    public function get(string $code = self::CODE, string $version = self::VERSION): ScoringDefinition
    {
        if ($code !== self::CODE || $version !== self::VERSION) {
            throw new DomainException('Unknown Prospect priority scoring definition.');
        }

        return new ScoringDefinition(
            self::CODE,
            self::VERSION,
            [
                'top_product_relevance' => ['polarity' => 'positive', 'weight' => 45],
                'additional_product_breadth' => ['polarity' => 'positive', 'weight' => 10, 'max_additional_products' => 2],
                'evidence_quality_and_confidence' => ['polarity' => 'positive', 'weight' => 10],
                'verified_public_corporate_channel' => ['polarity' => 'positive', 'weight' => 10, 'metadata_only' => true],
                'geography_and_logistics_fit' => ['polarity' => 'positive', 'weight' => 5],
                'evidence_freshness' => ['polarity' => 'positive', 'weight' => 5],
                'same_lane_relationship_signal' => ['polarity' => 'positive', 'weight' => 10, 'aggregate_only' => true],
                'review_completeness' => ['polarity' => 'positive', 'weight' => 5],
            ],
            ['unresolved_duplicate' => 40, 'no_product_relevance_at_least_30' => 35, 'stale_dossier' => 50],
            ['minimum' => 0, 'maximum' => 100, 'rounding' => 'nearest_integer', 'order' => ['positive', 'cap', 'eligibility']],
            ProductRelevanceDefinitionRegistry::bands(),
            ['product_confidence_weight' => 70, 'source_completeness_weight' => 30],
            ['stale_dossier_cap' => 50, 'mark_on' => ['context_changed', 'communication_state_changed', 'same_lane_aggregate_changed', 'definition_changed']],
            ['sales', 'procurement'],
            ['customer', 'supplier', 'prospective_customer', 'prospective_supplier', 'manufacturer'],
            ['positive' => 'Приоритет поддерживает фактор :factor.', 'unknown' => 'Недостаточно данных для :factor.', 'blocked' => 'Eligibility заблокирована: :factor.'],
        );
    }
}
