<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\DTO\Units\AggregateSupplySummary;
use App\Domain\AiSales\DTO\Units\CustomerOfferSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\DTO\Units\PublicGoodSummary;
use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\DTO\Units\SupportedRegionSummary;
use App\Domain\AiSales\DTO\Units\UnitBusinessContextSummary;
use App\Domain\AiSales\DTO\Units\UnitDuplicateCandidateSummary;
use App\Domain\AiSales\DTO\Units\UnitSharedPublicProfile;
use App\Domain\AiSales\DTO\Units\VerifiedPublicObservationEvidence;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Tools\Handlers\DisabledEntityProposalToolHandler;
use App\Domain\AiSales\Tools\Handlers\FindUnitDuplicateCandidatesToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetAggregateDemandSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetAggregateSupplySummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetCustomerOfferSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetPublicGoodsForProductToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetPublicGoodSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetPublicProductSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetSupportedRegionsToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetUnitBusinessContextSummaryToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetUnitPublicBusinessContactsToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetUnitSharedPublicProfileToolHandler;
use App\Domain\AiSales\Tools\Handlers\GetVerifiedPublicObservationEvidenceToolHandler;
use App\Domain\AiSales\Tools\Handlers\SearchPublicGoodsToolHandler;
use App\Domain\AiSales\Tools\Handlers\SearchPublicProductsToolHandler;
use App\Domain\AiSales\Tools\Handlers\SyntheticGoodToolHandler;
use LogicException;

class AiToolRegistry
{
    /** @var array<string, AiToolDefinition> */
    private array $definitions = [];

    /** @param null|list<AiToolDefinition> $definitions */
    public function __construct(?array $definitions = null)
    {
        foreach ($definitions ?? $this->defaults() as $definition) {
            $this->register($definition);
        }
    }

    public function register(AiToolDefinition $definition): void
    {
        $key = $this->key($definition->code, $definition->version);

        if (isset($this->definitions[$key])) {
            throw new LogicException("AI tool {$key} is already registered.");
        }

        $this->definitions[$key] = $definition;
    }

    public function get(string $code, string $version): AiToolDefinition
    {
        return $this->definitions[$this->key($code, $version)]
            ?? throw new PolicyViolation('unknown_tool_blocked', 'Unknown tools are blocked by the code-owned registry.');
    }

    /** @return list<AiToolDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /** @return list<AiToolDefinition> */
    private function defaults(): array
    {
        $purposes = AiPurpose::cases();
        $audiences = AiAudience::cases();
        $lanes = BusinessLane::cases();
        $roles = UnitRoleCode::cases();
        $contours = [AiProcessingContour::LocalRu, AiProcessingContour::ExternalSanitized];
        $publicScopes = [UnitVisibilityScope::SharedPublic];
        $execute = ['ai_sales.tools.execute'];
        $emptyInput = $this->object([], []);
        $publicGood = $this->object([
            'name' => $this->nullableString(255),
            'description' => $this->nullableString(1200),
            'published_attributes' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string', 'maxLength' => 255],
            ],
        ], ['name', 'description', 'published_attributes']);
        $publicProduct = $this->object([
            'product_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => $this->nullableString(255),
            'english_name' => $this->nullableString(255),
            'category' => $this->nullableString(255),
        ], ['product_id', 'name', 'english_name', 'category']);

        return [
            $this->definition(
                'catalog.get_synthetic_good',
                'Return one repository-owned fictional catalog item.',
                $this->object(['sku' => ['type' => 'string', 'const' => 'SYN-001']], ['sku']),
                $this->listOutput($publicGood, 1),
                [PublicGoodSummary::class],
                [...$execute, 'ai_sales.workflows.execute'],
                [AiPurpose::UnitResearch],
                [AiAudience::Internal],
                $lanes,
                $roles,
                [AiProcessingContour::ExternalSanitized],
                DataClassification::Public,
                $publicScopes,
                SyntheticGoodToolHandler::class,
                1,
                8_192,
                0,
                true,
                true,
            ),
            $this->definition(
                'catalog.search_public_goods',
                'Search bounded published catalog summaries.',
                $this->object([
                    'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    'sort' => ['type' => 'string', 'enum' => ['name_asc', 'name_desc']],
                ], ['query']),
                $this->listOutput($publicGood, 20),
                [PublicGoodSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                SearchPublicGoodsToolHandler::class,
                20,
                40_960,
                1,
            ),
            $this->definition(
                'catalog.search_public_products',
                'Search bounded published Product summaries without commercial relations.',
                $this->object([
                    'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    'sort' => ['type' => 'string', 'enum' => ['name_asc', 'name_desc']],
                ], ['query']),
                $this->listOutput($publicProduct, 20),
                [PublicProductSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                SearchPublicProductsToolHandler::class,
                20,
                40_960,
                1,
            ),
            $this->definition(
                'catalog.get_public_product_summary',
                'Read one bounded published Product summary without commercial relations.',
                $this->object(['product_id' => ['type' => 'integer', 'minimum' => 1]], ['product_id']),
                $this->listOutput($publicProduct, 1),
                [PublicProductSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetPublicProductSummaryToolHandler::class,
                1,
                8_192,
                1,
            ),
            $this->definition(
                'catalog.get_public_goods_for_product',
                'Read bounded published Goods for one published Product without supply-chain data.',
                $this->object([
                    'product_id' => ['type' => 'integer', 'minimum' => 1],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                    'sort' => ['type' => 'string', 'enum' => ['name_asc', 'name_desc']],
                ], ['product_id']),
                $this->listOutput($publicGood, 20),
                [PublicGoodSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetPublicGoodsForProductToolHandler::class,
                20,
                40_960,
                1,
            ),
            $this->definition(
                'catalog.get_public_good_summary',
                'Read one bounded published catalog summary.',
                $this->object(['good_id' => ['type' => 'integer', 'minimum' => 1]], ['good_id']),
                $this->listOutput($publicGood, 1),
                [PublicGoodSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetPublicGoodSummaryToolHandler::class,
                1,
                8_192,
                1,
            ),
            $this->definition(
                'geo.get_supported_regions',
                'List bounded supported region labels.',
                $this->object([
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'sort' => ['type' => 'string', 'enum' => ['name_asc', 'name_desc']],
                ], []),
                $this->listOutput($this->object([
                    'name' => $this->nullableString(255),
                    'country' => $this->nullableString(255),
                ], ['name', 'country']), 50),
                [SupportedRegionSummary::class],
                $execute,
                $purposes,
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetSupportedRegionsToolHandler::class,
                50,
                32_768,
                1,
            ),
            $this->definition(
                'unit.get_shared_public_profile',
                'Read the Stage 03 verified shared-public Unit profile.',
                $emptyInput,
                $this->listOutput($this->unitProfileSchema(), 1),
                [UnitSharedPublicProfile::class],
                $execute,
                [AiPurpose::UnitResearch, AiPurpose::BuyerDiscovery, AiPurpose::SupplierDiscovery],
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetUnitSharedPublicProfileToolHandler::class,
                1,
                24_576,
                6,
            ),
            $this->definition(
                'unit.get_business_context_summary',
                'Read one local-only Unit business context summary.',
                $emptyInput,
                $this->listOutput($this->contextSchema(), 1),
                [UnitBusinessContextSummary::class],
                $execute,
                $purposes,
                [AiAudience::Internal],
                $lanes,
                $roles,
                [AiProcessingContour::LocalRu],
                DataClassification::Internal,
                [UnitVisibilityScope::InternalOnly],
                GetUnitBusinessContextSummaryToolHandler::class,
                1,
                4_096,
                1,
            ),
            $this->definition(
                'unit.get_public_business_contacts',
                'Read verified business contacts locally; external personal values remain blocked.',
                $emptyInput,
                $this->listOutput($this->object([
                    'channel_type' => $this->nullableString(16),
                    'value' => $this->nullableString(512),
                    'source_label' => $this->nullableString(255),
                    'verified' => ['type' => 'boolean'],
                ], ['channel_type', 'value', 'source_label', 'verified']), 20),
                [PublicBusinessContactSummary::class],
                $execute,
                [AiPurpose::ContactDiscovery, AiPurpose::UnitResearch],
                [AiAudience::Internal],
                $lanes,
                $roles,
                [AiProcessingContour::LocalRu],
                DataClassification::PersonalData,
                $publicScopes,
                GetUnitPublicBusinessContactsToolHandler::class,
                20,
                16_384,
                5,
            ),
            $this->definition(
                'unit.get_verified_public_observation_evidence',
                'Read bounded verified public observations with public provenance.',
                $emptyInput,
                $this->listOutput($this->object([
                    'observation_key' => $this->nullableString(128),
                    'summary' => $this->nullableString(500),
                    'source_label' => $this->nullableString(255),
                    'source_reference' => $this->nullableString(512),
                    'observed_at' => $this->nullableString(40),
                ], ['observation_key', 'summary', 'source_label', 'source_reference', 'observed_at']), 20),
                [VerifiedPublicObservationEvidence::class],
                $execute,
                [AiPurpose::UnitResearch, AiPurpose::BuyerDiscovery, AiPurpose::SupplierDiscovery],
                $audiences,
                $lanes,
                $roles,
                $contours,
                DataClassification::Public,
                $publicScopes,
                GetVerifiedPublicObservationEvidenceToolHandler::class,
                20,
                32_768,
                1,
            ),
            $this->definition(
                'sales.get_aggregate_demand_summary',
                'Read a privacy-thresholded demand aggregate without customer rows.',
                $this->aggregateInput(),
                $this->listOutput($this->object([
                    'product_name' => $this->nullableString(255),
                    'period' => $this->nullableString(64),
                    'quantity_band' => $this->nullableString(96),
                    'region_count' => ['type' => 'integer', 'minimum' => 0],
                    'sample_size' => ['type' => 'integer', 'minimum' => 0],
                ], ['product_name', 'period', 'quantity_band', 'region_count', 'sample_size']), 1),
                [AggregateDemandSummary::class],
                [...$execute, 'ai_sales.classifications.view_internal'],
                [AiPurpose::ProductMatching, AiPurpose::ProcurementIntelligence, AiPurpose::OutreachDrafting],
                [AiAudience::Internal, AiAudience::Supplier, AiAudience::ProspectiveSupplier],
                [BusinessLane::Procurement],
                [UnitRoleCode::Supplier, UnitRoleCode::ProspectiveSupplier, UnitRoleCode::Manufacturer],
                [AiProcessingContour::LocalRu],
                DataClassification::CommercialConfidential,
                [UnitVisibilityScope::ProcurementLane],
                GetAggregateDemandSummaryToolHandler::class,
                1,
                4_096,
                5,
            ),
            $this->definition(
                'purchases.get_aggregate_supply_summary',
                'Read a privacy-thresholded supply aggregate without supplier rows.',
                $this->aggregateInput(),
                $this->listOutput($this->object([
                    'product_name' => $this->nullableString(255),
                    'region' => $this->nullableString(255),
                    'capacity_band' => $this->nullableString(96),
                    'supplier_count' => ['type' => 'integer', 'minimum' => 0],
                    'evidence_period' => $this->nullableString(64),
                ], ['product_name', 'region', 'capacity_band', 'supplier_count', 'evidence_period']), 1),
                [AggregateSupplySummary::class],
                [...$execute, 'ai_sales.classifications.view_internal'],
                [AiPurpose::ProductMatching, AiPurpose::SalesIntelligence, AiPurpose::OutreachDrafting],
                [AiAudience::Internal, AiAudience::Customer, AiAudience::ProspectiveCustomer],
                [BusinessLane::Sales],
                [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer, UnitRoleCode::Manufacturer],
                [AiProcessingContour::LocalRu],
                DataClassification::CommercialConfidential,
                [UnitVisibilityScope::SalesLane],
                GetAggregateSupplySummaryToolHandler::class,
                1,
                4_096,
                4,
            ),
            $this->definition(
                'crm.find_unit_duplicate_candidates',
                'Return bounded opaque duplicate candidates for authorized local review.',
                $emptyInput,
                $this->listOutput($this->object([
                    'candidate_reference' => $this->nullableString(64),
                    'match_reason' => $this->nullableString(64),
                ], ['candidate_reference', 'match_reason']), 20),
                [UnitDuplicateCandidateSummary::class],
                [...$execute, 'ai_sales.entity.propose'],
                [AiPurpose::UnitResearch],
                [AiAudience::Internal],
                $lanes,
                $roles,
                [AiProcessingContour::LocalRu],
                DataClassification::Internal,
                [UnitVisibilityScope::InternalOnly],
                FindUnitDuplicateCandidatesToolHandler::class,
                20,
                8_192,
                2,
            ),
            $this->definition(
                'pricing.get_customer_offer_summary',
                'Local-only approved offer summary; disabled pending a dedicated business workflow.',
                $this->object(['good_id' => ['type' => 'integer', 'minimum' => 1]], ['good_id']),
                $this->listOutput($this->object([
                    'good_name' => $this->nullableString(255),
                    'price' => $this->nullableString(64),
                    'currency' => $this->nullableString(8),
                    'measure' => $this->nullableString(64),
                    'valid_until' => $this->nullableString(32),
                ], ['good_name', 'price', 'currency', 'measure', 'valid_until']), 1),
                [CustomerOfferSummary::class],
                [...$execute, 'ai_sales.classifications.view_internal'],
                [AiPurpose::ProductMatching, AiPurpose::OutreachDrafting, AiPurpose::SalesIntelligence],
                [AiAudience::Internal, AiAudience::Customer, AiAudience::ProspectiveCustomer],
                [BusinessLane::Sales],
                [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer, UnitRoleCode::Manufacturer],
                [AiProcessingContour::LocalRu],
                DataClassification::CommercialConfidential,
                [UnitVisibilityScope::SalesLane],
                GetCustomerOfferSummaryToolHandler::class,
                1,
                4_096,
                1,
                false,
            ),
            $this->definition(
                'crm.propose_entity_candidate',
                'Metadata-only future proposal tool; model-initiated mutation is disabled.',
                $emptyInput,
                $this->listOutput($this->object(['status' => ['type' => 'string', 'const' => 'review_required']], ['status']), 1),
                [UnitDuplicateCandidateSummary::class],
                [...$execute, 'ai_sales.entity.propose'],
                [AiPurpose::UnitResearch],
                [AiAudience::Internal],
                $lanes,
                $roles,
                [AiProcessingContour::LocalRu],
                DataClassification::Internal,
                [UnitVisibilityScope::InternalOnly],
                DisabledEntityProposalToolHandler::class,
                0,
                1_024,
                0,
                false,
                false,
                'proposal_only',
                true,
            ),
        ];
    }

    private function definition(
        string $code,
        string $description,
        array $inputSchema,
        array $outputSchema,
        array $dtoClasses,
        array $permissions,
        array $purposes,
        array $audiences,
        array $lanes,
        array $roles,
        array $contours,
        DataClassification $classification,
        array $scopes,
        string $handler,
        int $rows,
        int $bytes,
        int $queries,
        bool $enabled = true,
        bool $syntheticOnly = false,
        string $sideEffect = 'read_only',
        bool $humanReviewRequired = false,
    ): AiToolDefinition {
        return new AiToolDefinition(
            $code,
            '1',
            $description,
            $inputSchema,
            $outputSchema,
            $dtoClasses,
            $permissions,
            $purposes,
            $audiences,
            $lanes,
            $roles,
            $contours,
            $classification,
            $scopes,
            $sideEffect,
            'run_step_tool_input_key',
            $rows,
            1_200,
            $bytes,
            5_000,
            $queries,
            2,
            '0.0000',
            $handler,
            $enabled,
            $syntheticOnly,
            false,
            $humanReviewRequired,
        );
    }

    private function aggregateInput(): array
    {
        return $this->object([
            'good_id' => ['type' => 'integer', 'minimum' => 1],
            'days' => ['type' => 'integer', 'enum' => [30, 90, 365]],
        ], ['good_id']);
    }

    private function unitProfileSchema(): array
    {
        $stringList = ['type' => 'array', 'maxItems' => 25, 'items' => ['type' => 'string', 'maxLength' => 1024]];

        return $this->object([
            'name' => $this->nullableString(255),
            'aliases' => $stringList,
            'industries' => $stringList,
            'cities' => $stringList,
            'public_uris' => $stringList,
            'observations' => $stringList,
        ], ['name', 'aliases', 'industries', 'cities', 'public_uris', 'observations']);
    }

    private function contextSchema(): array
    {
        return $this->object([
            'context_reference' => $this->nullableString(96),
            'lane' => $this->nullableString(32),
            'role_code' => $this->nullableString(32),
            'stage' => $this->nullableString(48),
            'status' => $this->nullableString(24),
            'owner_label' => $this->nullableString(255),
            'reviewer_label' => $this->nullableString(255),
            'primary_good_name' => $this->nullableString(255),
            'last_activity_at' => $this->nullableString(40),
        ], [
            'context_reference', 'lane', 'role_code', 'stage', 'status', 'owner_label',
            'reviewer_label', 'primary_good_name', 'last_activity_at',
        ]);
    }

    private function listOutput(array $itemSchema, int $maxItems): array
    {
        return $this->object([
            'items' => ['type' => 'array', 'maxItems' => $maxItems, 'items' => $itemSchema],
        ], ['items']);
    }

    private function object(array $properties, array $required): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $required,
            'properties' => $properties,
        ];
    }

    private function nullableString(int $maxLength): array
    {
        return ['type' => ['string', 'null'], 'maxLength' => $maxLength];
    }

    private function key(string $code, string $version): string
    {
        return $code.':'.$version;
    }
}
