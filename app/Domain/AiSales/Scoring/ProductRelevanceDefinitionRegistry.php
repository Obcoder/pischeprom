<?php

namespace App\Domain\AiSales\Scoring;

use DomainException;

final class ProductRelevanceDefinitionRegistry
{
    public const CODE = 'product_relevance.v1';

    public const VERSION = '1.0.0';

    public function get(string $code = self::CODE, string $version = self::VERSION): ScoringDefinition
    {
        if ($code !== self::CODE || $version !== self::VERSION) {
            throw new DomainException('Unknown Product relevance scoring definition.');
        }

        return new ScoringDefinition(
            self::CODE,
            self::VERSION,
            [
                'direct_product_mention' => ['polarity' => 'positive', 'weight' => 25, 'evidence_required' => true],
                'process_or_end_product_use' => ['polarity' => 'positive', 'weight' => 20, 'evidence_required' => true],
                'industry_activity_fit' => ['polarity' => 'positive', 'weight' => 15, 'evidence_required' => true],
                'verified_public_product_evidence' => ['polarity' => 'positive', 'weight' => 15, 'evidence_required' => true],
                'independent_source_corroboration' => ['polarity' => 'positive', 'weight' => 10, 'evidence_required' => true],
                'historical_same_lane_transaction' => ['polarity' => 'positive', 'weight' => 10, 'aggregate_only' => true],
                'geographic_serviceability' => ['polarity' => 'positive', 'weight' => 5, 'evidence_required' => true],
                'contradictory_evidence' => ['polarity' => 'negative', 'weight' => 20],
                'stale_evidence' => ['polarity' => 'negative', 'weight' => 10],
            ],
            [
                'directory_only_evidence' => 45,
                'no_primary_corporate_source' => 55,
                'unresolved_duplicate' => 40,
                'rejected_product_match' => 0,
            ],
            ['minimum' => 0, 'maximum' => 100, 'rounding' => 'integer', 'order' => ['positive', 'penalty', 'cap']],
            self::bands(),
            ['verified' => 100, 'unverified_ceiling' => 39, 'contradiction_penalty' => 20],
            ['stale_evidence_penalty_max' => 10, 'mark_on' => ['product_match_changed', 'evidence_changed', 'same_lane_aggregate_changed', 'definition_changed']],
            ['sales', 'procurement'],
            ['customer', 'supplier', 'prospective_customer', 'prospective_supplier', 'manufacturer'],
            ['positive' => 'Подтверждён фактор :factor.', 'negative' => 'Обнаружено ограничение :factor.', 'unknown' => 'Нет проверенных данных для :factor.'],
        );
    }

    public static function bands(): array
    {
        return ['low' => [0, 24], 'medium' => [25, 49], 'promising' => [50, 69], 'high' => [70, 84], 'very_high' => [85, 100]];
    }
}
