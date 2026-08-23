<?php

namespace App\Domain\AiSales\Prospecting;

enum ResultBusinessRole: string
{
    case PotentialBuyer = 'potential_buyer';
    case PossibleBuyer = 'possible_buyer';
    case SupplierOrCompetitor = 'supplier_or_competitor';
    case Marketplace = 'marketplace';
    case Retailer = 'retailer';
    case Directory = 'directory';
    case Informational = 'informational';
    case Irrelevant = 'irrelevant';
    case Unknown = 'unknown';
}
