<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use Illuminate\Validation\ValidationException;

class UnitVisibilityInvariant
{
    public function assert(
        Unit $unit,
        ?UnitBusinessContext $context,
        DataClassification $classification,
        UnitVisibilityScope $scope,
    ): void {
        if ($context && (int) $context->unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages([
                'unit_business_context_id' => 'The context does not belong to this Unit.',
            ]);
        }

        if ($scope === UnitVisibilityScope::SharedPublic) {
            if ($classification !== DataClassification::Public) {
                throw ValidationException::withMessages([
                    'data_classification' => 'Shared-public data must be explicitly classified as public.',
                ]);
            }

            if ($context !== null) {
                throw ValidationException::withMessages([
                    'unit_business_context_id' => 'Shared-public facts must not be bound to one lane context.',
                ]);
            }

            return;
        }

        if ($scope === UnitVisibilityScope::SalesLane && $context?->lane !== BusinessLane::Sales) {
            throw ValidationException::withMessages([
                'visibility_scope' => 'Sales-lane data requires a sales context.',
            ]);
        }

        if ($scope === UnitVisibilityScope::ProcurementLane && $context?->lane !== BusinessLane::Procurement) {
            throw ValidationException::withMessages([
                'visibility_scope' => 'Procurement-lane data requires a procurement context.',
            ]);
        }

        if (in_array($scope, [UnitVisibilityScope::SalesLane, UnitVisibilityScope::ProcurementLane], true) && ! $context) {
            throw ValidationException::withMessages([
                'unit_business_context_id' => 'Lane-scoped data requires a Unit business context.',
            ]);
        }
    }
}
