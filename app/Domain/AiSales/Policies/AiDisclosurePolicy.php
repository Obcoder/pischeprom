<?php

namespace App\Domain\AiSales\Policies;

use App\Domain\AiSales\Enums\AiAudience;
use App\Domain\AiSales\Enums\AiPurpose;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Enums\UnitVisibilityScope;

class AiDisclosurePolicy
{
    public function decide(AiDisclosureContext $context, AiClassifiedField $field): AiPolicyDecision
    {
        $fieldName = $field->subject.'.'.$field->field;

        if ($context->unitId <= 0 || $context->unitBusinessContextId <= 0) {
            return AiPolicyDecision::deny('context_identity_required', 'Unit and business context identity are required.', $fieldName);
        }

        if (! $context->role->allowsLane($context->lane)) {
            return AiPolicyDecision::deny('role_lane_mismatch', 'Role and lane are inconsistent.', $fieldName);
        }

        if (! $this->audienceMatchesContext($context)) {
            return AiPolicyDecision::deny('audience_context_mismatch', 'Audience does not match the Unit lane and role.', $fieldName);
        }

        if (! $this->purposeMatchesLane($context->purpose, $context->lane)) {
            return AiPolicyDecision::deny('purpose_lane_mismatch', 'Purpose is not valid for the requested lane.', $fieldName);
        }

        if ($field->classification === DataClassification::Secret) {
            return AiPolicyDecision::deny('secret_blocked', 'Secret fields are always blocked.', $fieldName);
        }

        if ($context->external && $field->classification === DataClassification::PersonalData) {
            return AiPolicyDecision::deny('personal_data_external_blocked', 'Personal data is blocked for external AI by default.', $fieldName);
        }

        if ($context->external && ! $field->externalExportable) {
            return AiPolicyDecision::deny('external_export_blocked', 'The field is not externally exportable.', $fieldName);
        }

        if (! in_array($context->purpose->value, $field->allowedPurposes, true)) {
            return AiPolicyDecision::deny('purpose_not_allowed', 'The field is not allowlisted for this purpose.', $fieldName);
        }

        if (! in_array($context->audience->value, $field->allowedAudiences, true)) {
            return AiPolicyDecision::deny('audience_not_allowed', 'The field is not allowlisted for this audience.', $fieldName);
        }

        if (! $this->scopeMatchesContext($field->visibilityScope, $context)) {
            return AiPolicyDecision::deny('visibility_scope_mismatch', 'The field belongs to a different visibility compartment.', $fieldName);
        }

        if ($context->audience === AiAudience::Customer || $context->audience === AiAudience::ProspectiveCustomer) {
            if ($field->visibilityScope === UnitVisibilityScope::ProcurementLane) {
                return AiPolicyDecision::deny('procurement_disclosure_blocked', 'Customer audiences cannot receive procurement data.', $fieldName);
            }
        }

        if ($context->audience === AiAudience::Supplier || $context->audience === AiAudience::ProspectiveSupplier) {
            if ($field->visibilityScope === UnitVisibilityScope::SalesLane) {
                return AiPolicyDecision::deny('sales_disclosure_blocked', 'Supplier audiences cannot receive sales data.', $fieldName);
            }
        }

        return AiPolicyDecision::allow($fieldName);
    }

    private function audienceMatchesContext(AiDisclosureContext $context): bool
    {
        return match ($context->audience) {
            AiAudience::Internal => true,
            AiAudience::Customer, AiAudience::ProspectiveCustomer => $context->lane === BusinessLane::Sales
                && in_array($context->role, [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer], true),
            AiAudience::Supplier, AiAudience::ProspectiveSupplier => $context->lane === BusinessLane::Procurement
                && in_array($context->role, [
                    UnitRoleCode::Supplier,
                    UnitRoleCode::ProspectiveSupplier,
                    UnitRoleCode::Manufacturer,
                ], true),
        };
    }

    private function purposeMatchesLane(AiPurpose $purpose, BusinessLane $lane): bool
    {
        return match ($purpose) {
            AiPurpose::BuyerDiscovery, AiPurpose::SalesIntelligence => $lane === BusinessLane::Sales,
            AiPurpose::SupplierDiscovery, AiPurpose::ProcurementIntelligence => $lane === BusinessLane::Procurement,
            default => true,
        };
    }

    private function scopeMatchesContext(UnitVisibilityScope $scope, AiDisclosureContext $context): bool
    {
        return match ($scope) {
            UnitVisibilityScope::SharedPublic => true,
            UnitVisibilityScope::SalesLane => $context->lane === BusinessLane::Sales,
            UnitVisibilityScope::ProcurementLane => $context->lane === BusinessLane::Procurement,
            UnitVisibilityScope::InternalOnly => ! $context->external && $context->audience === AiAudience::Internal,
        };
    }
}
