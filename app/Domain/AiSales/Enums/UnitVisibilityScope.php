<?php

namespace App\Domain\AiSales\Enums;

enum UnitVisibilityScope: string
{
    case SharedPublic = 'shared_public';
    case SalesLane = 'sales_lane';
    case ProcurementLane = 'procurement_lane';
    case InternalOnly = 'internal_only';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
