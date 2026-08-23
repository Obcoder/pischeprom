<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum CommunicationEvidenceType: string
{
    case SignedDocumentedConsent = 'signed_documented_consent';
    case WebFormConsent = 'web_form_consent';
    case WrittenResponse = 'written_response';
    case ContractRelationshipEvidence = 'contract_relationship_evidence';
    case ImportManualEvidence = 'import_manual_evidence';
    case OtherReviewed = 'other_reviewed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
