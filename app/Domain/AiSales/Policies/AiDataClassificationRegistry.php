<?php

namespace App\Domain\AiSales\Policies;

use App\Domain\AiSales\DTO\Prospecting\PublicCompanyResearchInput;
use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\DTO\Units\AggregateSupplySummary;
use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\DTO\Units\SanitizedEntityLegalSummary;
use App\Domain\AiSales\DTO\Units\SupportedRegionSummary;
use App\Domain\AiSales\DTO\Units\UnitBusinessContextSummary;
use App\Domain\AiSales\DTO\Units\UnitDuplicateCandidateSummary;
use App\Domain\AiSales\DTO\Units\UnitSharedPublicProfile;
use App\Domain\AiSales\DTO\Units\VerifiedPublicObservationEvidence;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;

class AiDataClassificationRegistry
{
    private array $rules = [];

    public function __construct()
    {
        $allPurposes = AiPurpose::values();
        $allAudiences = AiAudience::values();
        $publicResearchPurposes = [
            AiPurpose::BuyerDiscovery->value,
            AiPurpose::SupplierDiscovery->value,
            AiPurpose::UnitResearch->value,
            AiPurpose::ContactDiscovery->value,
            AiPurpose::ProductMatching->value,
            AiPurpose::ProspectScoring->value,
            AiPurpose::OutreachDrafting->value,
        ];

        $this->registerMany(PublicGoodSummary::class, [
            'name', 'description', 'published_attributes',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, $publicResearchPurposes, $allAudiences, true, 'none', 'Published catalogue fields only.');

        $this->registerMany(PublicProductSummary::class, [
            'product_id', 'name', 'english_name', 'category',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, $publicResearchPurposes, $allAudiences, true, 'none', 'Explicit published Product fields; manufacturers, consumers, components, suppliers, prices, and margins are excluded.');

        $this->registerMany(PublicCompanyResearchInput::class, [
            'domain', 'page_title', 'meta_description', 'headings', 'visible_text',
            'product_names', 'geography', 'trust_level', 'instruction_authority',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, [
            AiPurpose::BuyerDiscovery->value,
            AiPurpose::SupplierDiscovery->value,
        ], [
            AiAudience::ProspectiveCustomer->value,
            AiAudience::ProspectiveSupplier->value,
        ], true, 'none', 'Bounded public-web evidence marked untrusted; contact values and raw HTML are excluded.');

        $this->registerMany(UnitSharedPublicProfile::class, [
            'name', 'aliases', 'industries', 'cities', 'public_uris', 'observations',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, $allPurposes, $allAudiences, true, 'none', 'Verified shared-public Unit profile.');

        $this->registerMany(SupportedRegionSummary::class, [
            'name', 'country',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, $publicResearchPurposes, $allAudiences, true, 'none', 'Published geographic labels only.');

        $this->registerMany(VerifiedPublicObservationEvidence::class, [
            'observation_key', 'summary', 'source_label', 'source_reference', 'observed_at',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, [
            AiPurpose::UnitResearch->value,
            AiPurpose::BuyerDiscovery->value,
            AiPurpose::SupplierDiscovery->value,
        ], $allAudiences, true, 'none', 'Verified shared-public evidence with bounded provenance metadata.');

        $customerAudiences = [AiAudience::Internal->value, AiAudience::Customer->value, AiAudience::ProspectiveCustomer->value];
        $this->registerMany(CustomerOfferSummary::class, [
            'good_name', 'price', 'currency', 'measure', 'valid_until',
        ], DataClassification::CommercialConfidential, UnitVisibilityScope::SalesLane, [
            AiPurpose::ProductMatching->value,
            AiPurpose::OutreachDrafting->value,
            AiPurpose::SalesIntelligence->value,
        ], $customerAudiences, true, 'approved_summary', 'Explicit customer-offer summary without cost or margin.');

        $this->registerMany(UnitBusinessContextSummary::class, [
            'context_reference', 'lane', 'role_code', 'stage', 'status', 'owner_label',
            'reviewer_label', 'primary_good_name', 'last_activity_at',
        ], DataClassification::Internal, UnitVisibilityScope::InternalOnly, $allPurposes, [AiAudience::Internal->value], false, 'none', 'Operational CRM context remains local.');

        $this->registerMany(UnitDuplicateCandidateSummary::class, [
            'candidate_reference', 'match_reason',
        ], DataClassification::Internal, UnitVisibilityScope::InternalOnly, [
            AiPurpose::UnitResearch->value,
        ], [AiAudience::Internal->value], false, 'none', 'Opaque duplicate candidates are local-only and require an authorized Unit context.');

        $this->registerMany(SanitizedEntityLegalSummary::class, [
            'legal_name', 'entity_type', 'country',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, [
            AiPurpose::UnitResearch->value,
            AiPurpose::ProductMatching->value,
        ], $allAudiences, true, 'none', 'Allowlisted legal summary excludes banking and raw registry payloads.');
        $this->registerMany(SanitizedEntityLegalSummary::class, [
            'registry_identifier_masked',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, [
            AiPurpose::UnitResearch->value,
            AiPurpose::ProductMatching->value,
        ], $allAudiences, true, 'mask', 'Registry identifiers are masked deterministically before disclosure.');

        $supplierAudiences = [AiAudience::Internal->value, AiAudience::Supplier->value, AiAudience::ProspectiveSupplier->value];
        $this->registerMany(AggregateDemandSummary::class, [
            'product_name', 'period', 'quantity_band', 'region_count', 'sample_size',
        ], DataClassification::CommercialConfidential, UnitVisibilityScope::ProcurementLane, [
            AiPurpose::ProductMatching->value,
            AiPurpose::ProcurementIntelligence->value,
            AiPurpose::OutreachDrafting->value,
        ], $supplierAudiences, true, 'aggregate_only', 'Demand is aggregated without customer identities or transaction rows.');

        $this->registerMany(AggregateSupplySummary::class, [
            'product_name', 'region', 'capacity_band', 'supplier_count', 'evidence_period',
        ], DataClassification::CommercialConfidential, UnitVisibilityScope::SalesLane, [
            AiPurpose::ProductMatching->value,
            AiPurpose::SalesIntelligence->value,
            AiPurpose::OutreachDrafting->value,
        ], $customerAudiences, true, 'aggregate_only', 'Supply is aggregated without supplier identities, costs or terms.');

        $this->registerMany(PublicBusinessContactSummary::class, [
            'channel_type', 'source_label', 'verified',
        ], DataClassification::Public, UnitVisibilityScope::SharedPublic, [
            AiPurpose::ContactDiscovery->value,
            AiPurpose::UnitResearch->value,
        ], $allAudiences, true, 'none', 'Contact metadata contains no address or number.');
        $this->registerMany(PublicBusinessContactSummary::class, [
            'value',
        ], DataClassification::PersonalData, UnitVisibilityScope::SharedPublic, [
            AiPurpose::ContactDiscovery->value,
        ], [AiAudience::Internal->value], false, 'block_external', 'Email and telephone values are personal data by default.');

        foreach (['password', 'token', 'api_key', 'authorization', 'bearer_token', 'cookie', 'session', 'private_key', 'secret', '.env', 'env', 'two_factor_secret', 'remember_token'] as $field) {
            $this->register(new AiClassifiedField(
                'security.credentials',
                $field,
                DataClassification::Secret,
                UnitVisibilityScope::InternalOnly,
                [],
                [],
                false,
                'block',
                'Authentication and secret material is never disclosed.',
            ));
        }

        foreach (['body', 'html', 'headers', 'attachments'] as $field) {
            $this->register(new AiClassifiedField(
                'raw_correspondence',
                $field,
                DataClassification::PersonalData,
                UnitVisibilityScope::InternalOnly,
                [],
                [AiAudience::Internal->value],
                false,
                'block_external',
                'Raw correspondence is not an export DTO.',
            ));
        }
    }

    public function find(string $subject, string $field): ?AiClassifiedField
    {
        return $this->rules[$this->key($subject, $field)] ?? null;
    }

    public function registeredFields(string $subject): array
    {
        return collect($this->rules)
            ->filter(fn (AiClassifiedField $rule) => $rule->subject === $subject)
            ->pluck('field')
            ->sort()
            ->values()
            ->all();
    }

    private function registerMany(
        string $subject,
        array $fields,
        DataClassification $classification,
        UnitVisibilityScope $scope,
        array $purposes,
        array $audiences,
        bool $externalExportable,
        string $redactionRule,
        string $justification,
    ): void {
        foreach ($fields as $field) {
            $this->register(new AiClassifiedField(
                $subject,
                $field,
                $classification,
                $scope,
                $purposes,
                $audiences,
                $externalExportable,
                $redactionRule,
                $justification,
            ));
        }
    }

    private function register(AiClassifiedField $rule): void
    {
        $this->rules[$this->key($rule->subject, $rule->field)] = $rule;
    }

    private function key(string $subject, string $field): string
    {
        return $subject.'::'.$field;
    }
}
