<?php

namespace App\Domain\AiSales\Prospecting;

use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Support\AiCanonicalJson;

class ProspectingQueryTemplateRegistry
{
    public const VERSION = 'stage09-v1';

    /** @return list<array{code: string, version: string, name_source: string, intent: string, terms: list<string>}> */
    public function forPurpose(ProspectingPurpose $purpose): array
    {
        return match ($purpose) {
            ProspectingPurpose::BuyerDiscovery => [
                [
                    'code' => 'buyer.product_consumers_ru',
                    'version' => self::VERSION,
                    'name_source' => 'rus',
                    'intent' => 'b2b_product_buyer',
                    'terms' => ['производитель', 'закупает', 'оптом'],
                ],
                [
                    'code' => 'buyer.product_users_bilingual',
                    'version' => self::VERSION,
                    'name_source' => 'eng',
                    'intent' => 'b2b_product_consumer',
                    'terms' => ['manufacturer', 'buyer', 'wholesale'],
                ],
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
