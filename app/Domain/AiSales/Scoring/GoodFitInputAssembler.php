<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\UnitBusinessContext;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoodFitInputAssembler
{
    public function __construct(
        private readonly UnitContextAuthorizationService $authorization,
        private readonly GoodProductMappingResolver $mappings,
    ) {}

    public function assemble(User $actor, UnitGoodMatch $subject): ScoringInput
    {
        $match = UnitGoodMatch::query()->without(['good'])->select([
            'id', 'unit_id', 'unit_business_context_id', 'unit_product_match_id', 'good_id',
            'match_type', 'status', 'fit_status', 'compatibility_state', 'evidence_reference', 'evidence_hash',
            'stale_after',
        ])->findOrFail($subject->id);
        $context = UnitBusinessContext::query()->select(['id', 'unit_id', 'lane', 'role_code', 'stage', 'status'])
            ->findOrFail($match->unit_business_context_id);
        if ((int) $context->unit_id !== (int) $match->unit_id || ! $match->unit_product_match_id) {
            throw new NotFoundHttpException('Good fit context binding is invalid.');
        }
        $this->authorization->authorizeLane($actor, $context->lane);
        $productMatch = UnitProductMatch::query()->without(['product'])->select([
            'id', 'unit_id', 'unit_business_context_id', 'product_id', 'match_type', 'status',
        ])->findOrFail($match->unit_product_match_id);
        if ((int) $productMatch->unit_id !== (int) $match->unit_id
            || (int) $productMatch->unit_business_context_id !== (int) $context->id) {
            throw new NotFoundHttpException('Good fit Product binding is invalid.');
        }
        $directionValid = match ($context->lane) {
            BusinessLane::Sales => in_array($match->match_type->value, ['potential_need', 'cross_sell'], true)
                && $match->match_type->value === $productMatch->match_type->value,
            BusinessLane::Procurement => $match->match_type->value === 'potential_offer'
                && $match->match_type->value === $productMatch->match_type->value,
            default => false,
        };

        $good = DB::table('goods')->select(['id', 'is_published'])->where('id', $match->good_id)->first();
        if (! $good) {
            throw new NotFoundHttpException('Good not found.');
        }
        $productIds = $this->mappings->distinctProductIds((int) $match->good_id, 2);
        $mappingState = match (count($productIds)) {
            0 => ProductMappingState::MissingProductMapping,
            1 => $productIds[0] === (int) $productMatch->product_id
                ? ProductMappingState::Mapped : ProductMappingState::ProductScopeMismatch,
            default => ProductMappingState::AmbiguousProductMapping,
        };
        $evidence = $mappingState === ProductMappingState::Mapped ? [[
            'factor_code' => 'exact_product_mapping',
            'type' => 'good_product_mapping',
            'reference' => 'good-product:'.$match->good_id.':'.$productMatch->product_id,
            'hash' => hash('sha256', 'good-product:'.$match->good_id.':'.$productMatch->product_id),
            'confidence' => 100,
            'verified' => true,
            'at' => null,
        ]] : [];

        // The Stage 10 audit found no approved, context-bound commercial attribute inputs.
        // Null means unknown and is deliberately different from a negative match.
        return new ScoringInput('good_fit', [
            'unit_good_match_id' => (int) $match->id,
            'unit_product_match_id' => (int) $productMatch->id,
            'unit_id' => (int) $match->unit_id,
            'unit_business_context_id' => (int) $context->id,
            'good_id' => (int) $match->good_id,
            'product_id' => (int) $productMatch->product_id,
        ], [
            'lane' => $context->lane->value,
            'role_code' => $context->role_code->value,
            'mapping_state' => $mappingState->value,
            'product_match_active' => ! in_array($productMatch->status, [UnitProductMatchStatus::Rejected, UnitProductMatchStatus::Stale], true),
            'good_match_active' => ! in_array($match->status, [UnitGoodMatchStatus::Rejected, UnitGoodMatchStatus::Stale], true),
            'product_id_matches' => $mappingState === ProductMappingState::Mapped,
            'good_published' => (bool) $good->is_published,
            'format_or_processing_fit' => null,
            'packaging_or_moq_fit' => null,
            'origin_grade_size_fit' => null,
            'regional_delivery_or_supply_fit' => null,
            'approved_availability_signal' => null,
            'approved_price_fit' => null,
            'stale_commercial' => $match->fit_status === GoodOfferFitStatus::Stale || ($match->stale_after && $match->stale_after->isPast()),
            'missing_essential_offer_data' => true,
            'policy_blocked' => ! in_array($context->lane, [BusinessLane::Sales, BusinessLane::Procurement], true)
                || ! $directionValid || $context->status->value !== 'active',
        ], $evidence);
    }
}
