<?php

namespace App\Domain\AiSales\Enums;

enum AiPurpose: string
{
    case BuyerDiscovery = 'buyer_discovery';
    case SupplierDiscovery = 'supplier_discovery';
    case UnitResearch = 'unit_research';
    case ContactDiscovery = 'contact_discovery';
    case ProductMatching = 'product_matching';
    case ProspectScoring = 'prospect_scoring';
    case OutreachDrafting = 'outreach_drafting';
    case ReplyTriage = 'reply_triage';
    case FollowupRecommendation = 'followup_recommendation';
    case SalesIntelligence = 'sales_intelligence';
    case ProcurementIntelligence = 'procurement_intelligence';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
