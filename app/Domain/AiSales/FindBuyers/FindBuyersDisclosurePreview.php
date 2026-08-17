<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\DTO\Units\AggregateDemandSummary;
use App\Domain\AiSales\DTO\Units\PublicBusinessContactSummary;
use App\Domain\AiSales\DTO\Units\PublicProductSummary;
use App\Domain\AiSales\DTO\Units\UnitBusinessContextSummary;
use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Policies\AiDisclosurePolicy;
use App\Domain\AiSales\Support\AiCanonicalJson;

final class FindBuyersDisclosurePreview
{
    public function __construct(
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiDisclosurePolicy $policy,
    ) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $context = new AiDisclosureContext(
            1,
            1,
            BusinessLane::Sales,
            UnitRoleCode::ProspectiveCustomer,
            AiAudience::ProspectiveCustomer,
            AiPurpose::BuyerDiscovery,
            true,
        );
        $allowed = collect($this->registry->registeredFields(PublicProductSummary::class))
            ->map(function (string $field) use ($context): array {
                $rule = $this->registry->find(PublicProductSummary::class, $field);
                $decision = $rule ? $this->policy->decide($context, $rule) : null;

                return [
                    'field' => $field,
                    'classification' => $rule?->classification->value ?? 'unclassified',
                    'visibility_scope' => $rule?->visibilityScope->value ?? 'internal_only',
                    'external_exportable' => $rule?->externalExportable ?? false,
                    'decision' => $decision?->allowed ? 'allow' : 'block',
                    'reason_code' => $decision?->code ?? 'unclassified_field',
                ];
            })->filter(fn (array $row): bool => $row['decision'] === 'allow')->values()->all();

        $blocked = collect([
            ['subject' => 'security.credentials', 'field' => 'token', 'label' => 'credentials_and_secrets'],
            ['subject' => PublicBusinessContactSummary::class, 'field' => 'value', 'label' => 'personal_contact_values'],
            ['subject' => 'raw_correspondence', 'field' => 'body', 'label' => 'raw_correspondence'],
            ['subject' => AggregateDemandSummary::class, 'field' => 'product_name', 'label' => 'procurement_and_supplier_lane_data'],
            ['subject' => UnitBusinessContextSummary::class, 'field' => 'owner_label', 'label' => 'internal_crm_context'],
        ])->map(function (array $item) use ($context): array {
            $rule = $this->registry->find($item['subject'], $item['field']);
            $decision = $rule ? $this->policy->decide($context, $rule) : null;

            return [
                'code' => $item['label'],
                'classification' => $rule?->classification->value ?? 'unclassified',
                'visibility_scope' => $rule?->visibilityScope->value ?? 'internal_only',
                'decision' => 'block',
                'reason_code' => $decision?->code ?? 'unclassified_field',
            ];
        })->push([
            'code' => 'contracts_invoices_payments_margins_and_purchase_prices',
            'classification' => 'unclassified',
            'visibility_scope' => 'internal_only',
            'decision' => 'block',
            'reason_code' => 'unclassified_field',
        ])->values()->all();

        $payload = [
            'policy_version' => (string) config('ai-sales.policy_versions.disclosure', 'stage03-v1'),
            'preview_version' => (string) config('ai-sales.policy_versions.find_buyers_disclosure', 'stage11-v1'),
            'purpose' => AiPurpose::BuyerDiscovery->value,
            'audience' => AiAudience::ProspectiveCustomer->value,
            'lane' => BusinessLane::Sales->value,
            'role_code' => UnitRoleCode::ProspectiveCustomer->value,
            'external_preview' => true,
            'allowed_fields' => $allowed,
            'blocked_classes' => $blocked,
            'raw_bodies_exported' => false,
            'requires_runtime_context_recheck' => true,
        ];

        return [...$payload, 'policy_hash' => AiCanonicalJson::hash($payload)];
    }
}
