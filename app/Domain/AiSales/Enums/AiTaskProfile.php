<?php

namespace App\Domain\AiSales\Enums;

enum AiTaskProfile: string
{
    case InternalReplyTriage = 'internal_reply_triage';
    case InternalPriceListExtraction = 'internal_price_list_extraction';
    case InternalDossierSummary = 'internal_dossier_summary';
    case SanitizedBriefGeneration = 'sanitized_brief_generation';
    case PublicCompanyResearch = 'public_company_research';
    case PublicToolWorkflow = 'public_tool_workflow';
    case OutreachDrafting = 'outreach_drafting';
    case ComplexMarketAnalysis = 'complex_market_analysis';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
