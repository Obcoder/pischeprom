<?php

namespace App\Domain\AiSales\Enums;

enum ProspectingPurpose: string
{
    case BuyerDiscovery = 'buyer_discovery';
    case SupplierDiscovery = 'supplier_discovery';

    public function lane(): BusinessLane
    {
        return match ($this) {
            self::BuyerDiscovery => BusinessLane::Sales,
            self::SupplierDiscovery => BusinessLane::Procurement,
        };
    }

    public function role(): UnitRoleCode
    {
        return match ($this) {
            self::BuyerDiscovery => UnitRoleCode::ProspectiveCustomer,
            self::SupplierDiscovery => UnitRoleCode::ProspectiveSupplier,
        };
    }

    public function goodMatchType(): UnitGoodMatchType
    {
        return match ($this) {
            self::BuyerDiscovery => UnitGoodMatchType::PotentialNeed,
            self::SupplierDiscovery => UnitGoodMatchType::PotentialOffer,
        };
    }

    public function productMatchType(): UnitProductMatchType
    {
        return match ($this) {
            self::BuyerDiscovery => UnitProductMatchType::PotentialNeed,
            self::SupplierDiscovery => UnitProductMatchType::PotentialOffer,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
