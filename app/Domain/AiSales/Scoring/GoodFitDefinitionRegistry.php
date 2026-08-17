<?php

namespace App\Domain\AiSales\Scoring;

use DomainException;

final class GoodFitDefinitionRegistry
{
    public const CODE = 'good_fit.v1';

    public const VERSION = '1.0.0';

    public function get(string $code = self::CODE, string $version = self::VERSION): ScoringDefinition
    {
        if ($code !== self::CODE || $version !== self::VERSION) {
            throw new DomainException('Unknown Good fit scoring definition.');
        }

        return new ScoringDefinition(
            self::CODE,
            self::VERSION,
            [
                'exact_product_mapping' => ['polarity' => 'positive', 'weight' => 20, 'prerequisite' => true],
                'format_or_processing_fit' => ['polarity' => 'positive', 'weight' => 20],
                'packaging_or_moq_fit' => ['polarity' => 'positive', 'weight' => 15],
                'origin_grade_size_fit' => ['polarity' => 'positive', 'weight' => 15],
                'regional_delivery_or_supply_fit' => ['polarity' => 'positive', 'weight' => 10],
                'approved_availability_signal' => ['polarity' => 'positive', 'weight' => 10],
                'approved_price_fit' => ['polarity' => 'positive', 'weight' => 10],
            ],
            [
                'inactive_unpublished_good' => 20,
                'stale_price_or_availability' => 50,
                'missing_essential_offer_data' => 60,
                'no_commercial_fields_beyond_product' => 45,
            ],
            ['minimum' => 0, 'maximum' => 100, 'rounding' => 'integer', 'order' => ['prerequisite', 'positive', 'cap', 'block']],
            ProductRelevanceDefinitionRegistry::bands(),
            ['known_verified' => 100, 'unknown' => 0],
            ['stale_commercial_cap' => 50, 'mark_on' => ['good_match_changed', 'product_mapping_changed', 'evidence_changed', 'definition_changed']],
            ['sales', 'procurement'],
            ['customer', 'supplier', 'prospective_customer', 'prospective_supplier', 'manufacturer'],
            ['positive' => 'Подтверждено соответствие :factor.', 'unknown' => 'Параметр :factor не подтверждён.', 'blocked' => 'Оценка заблокирована: :factor.'],
        );
    }
}
