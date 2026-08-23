<?php

namespace App\Domain\AiSales\Enums;

enum AiAudience: string
{
    case Internal = 'internal';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case ProspectiveCustomer = 'prospective_customer';
    case ProspectiveSupplier = 'prospective_supplier';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
