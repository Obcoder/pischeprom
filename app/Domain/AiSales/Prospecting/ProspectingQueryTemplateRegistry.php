<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Support\AiCanonicalJson;

class ProspectingQueryTemplateRegistry
{
    public const VERSION = 'buyer-query-matrix-v2';

    private const BUYER_INTENTS = [
        'company_discovery' => ['компания', 'официальный сайт'],
        'manufacturer_discovery' => ['производитель', 'производство'],
        'production_activity' => ['производственная компания', 'виды деятельности'],
        'product_usage_evidence' => ['продукция', 'состав', 'ассортимент'],
        'procurement_evidence' => ['закупки', 'сырье', 'ингредиенты'],
        'institutional_buyer' => ['комбинат питания', 'центральная кухня'],
    ];

    /** @return list<array{code: string, version: string, name_source: string, intent: string, terms: list<string>}> */
    public function forPurpose(ProspectingPurpose $purpose): array
    {
        return match ($purpose) {
            ProspectingPurpose::BuyerDiscovery => [
                ...collect(self::BUYER_INTENTS)->map(fn (array $terms, string $intent): array => [
                    'code' => 'buyer.matrix.'.$intent,
                    'version' => self::VERSION,
                    'name_source' => 'rus',
                    'intent' => $intent,
                    'terms' => $terms,
                ])->values()->all(),
            ],
            ProspectingPurpose::SupplierDiscovery => [
                [
                    'code' => 'supplier.product_producers_ru',
                    'version' => self::VERSION,
                    'name_source' => 'rus',
                    'intent' => 'b2b_product_supplier',
                    'terms' => ['производитель', 'поставщик', 'оптом'],
                ],
                [
                    'code' => 'supplier.product_distributors_bilingual',
                    'version' => self::VERSION,
                    'name_source' => 'eng',
                    'intent' => 'b2b_product_distributor',
                    'terms' => ['distributor', 'supplier', 'wholesale'],
                ],
            ],
        };
    }

    /** @return list<string> */
    public function buyerIntentCodes(): array
    {
        return array_keys(self::BUYER_INTENTS);
    }

    public function templateHash(array $template): string
    {
        return AiCanonicalJson::hash($template);
    }

    public function registryHash(): string
    {
        return AiCanonicalJson::hash([
            'version' => self::VERSION,
            'buyer' => $this->forPurpose(ProspectingPurpose::BuyerDiscovery),
            'supplier' => $this->forPurpose(ProspectingPurpose::SupplierDiscovery),
        ]);
    }
}
