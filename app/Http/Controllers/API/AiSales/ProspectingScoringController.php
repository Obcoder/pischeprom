<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\ScoreReviewStatus;
use App\Domain\AiSales\Scoring\GoodFitDefinitionRegistry;
use App\Domain\AiSales\Scoring\ProductRelevanceDefinitionRegistry;
use App\Domain\AiSales\Scoring\ProspectingScoreRecalculationService;
use App\Domain\AiSales\Scoring\ProspectingScoreReviewService;
use App\Domain\AiSales\Scoring\ProspectingScoringFeatureGuard;
use App\Domain\AiSales\Scoring\ProspectPriorityDefinitionRegistry;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\OverrideProspectingScoreRequest;
use App\Http\Requests\AiSales\RecalculateProspectingScoreRequest;
use App\Http\Requests\AiSales\ReviewProspectingScoreRequest;
use App\Http\Resources\AiSales\ProspectingScoreSnapshotResource;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitGoodFitSnapshot;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\UnitProspectPrioritySnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProspectingScoringController extends Controller
{
    public function definitions(
        Request $request,
        ProspectingScoringFeatureGuard $features,
        UnitContextAuthorizationService $authorization,
        ProductRelevanceDefinitionRegistry $product,
        GoodFitDefinitionRegistry $good,
        ProspectPriorityDefinitionRegistry $priority,
    ): JsonResponse {
        $features->scoring();
        abort_unless(
            $authorization->hasPermission($request->user(), ProspectingAuthorizationService::VIEW_SCORING_DEFINITIONS)
            && $authorization->hasPermission($request->user(), ProspectingAuthorizationService::VIEW),
            403,
        );

        return response()->json(['data' => [
            $product->get()->safeArray(), $good->get()->safeArray(), $priority->get()->safeArray(),
        ]]);
    }

    public function scores(
        Request $request,
        Unit $unit,
        UnitBusinessContext $context,
        ProspectingScoringFeatureGuard $features,
        UnitContextAuthorizationService $contexts,
        ProspectingAuthorizationService $authorization,
    ): JsonResponse {
        $features->scoring();
        $contexts->assertContextBelongsToUnit($unit, $context);
        Gate::authorize('view', $context);
        $authorization->authorize($request->user(), ProspectingAuthorizationService::VIEW_SCORING, $context->lane);

        return response()->json(['data' => [
            'product_relevance' => $this->collection(UnitProductRelevanceSnapshot::query()->where('unit_business_context_id', $context->id)),
            'good_fit' => $this->collection(UnitGoodFitSnapshot::query()->where('unit_business_context_id', $context->id)),
            'prospect_priority' => $this->collection(UnitProspectPrioritySnapshot::query()->where('unit_business_context_id', $context->id)),
            'capabilities' => [
                'view' => true,
                'recalculate' => $authorization->can($request->user(), ProspectingAuthorizationService::RECALCULATE_SCORING, $context->lane),
                'review' => $authorization->can($request->user(), ProspectingAuthorizationService::REVIEW_SCORING, $context->lane),
                'override' => (bool) config('ai-sales.prospecting.score_overrides_enabled', false)
                    && $authorization->can($request->user(), ProspectingAuthorizationService::OVERRIDE_SCORING, $context->lane),
            ],
        ]]);
    }

    public function recalculateProduct(RecalculateProspectingScoreRequest $request, UnitProductMatch $unitProductMatch, ProspectingScoreRecalculationService $service, ProspectingAuthorizationService $authorization): JsonResponse
    {
        Gate::authorize('view', $unitProductMatch);
        $authorization->authorize($request->user(), ProspectingAuthorizationService::RECALCULATE_SCORING, $unitProductMatch->businessContext()->firstOrFail()->lane);
        $snapshot = $service->product($request->user(), $unitProductMatch);

        return $this->one($request, $snapshot);
    }

    public function recalculateGood(RecalculateProspectingScoreRequest $request, UnitGoodMatch $unitGoodMatch, ProspectingScoreRecalculationService $service, ProspectingAuthorizationService $authorization): JsonResponse
    {
        Gate::authorize('view', $unitGoodMatch);
        $authorization->authorize($request->user(), ProspectingAuthorizationService::RECALCULATE_SCORING, $unitGoodMatch->businessContext()->firstOrFail()->lane);
        $snapshot = $service->good($request->user(), $unitGoodMatch);

        return $this->one($request, $snapshot);
    }

    public function recalculatePriority(RecalculateProspectingScoreRequest $request, UnitBusinessContext $context, ProspectingScoreRecalculationService $service, ProspectingAuthorizationService $authorization): JsonResponse
    {
        Gate::authorize('view', $context);
        $authorization->authorize($request->user(), ProspectingAuthorizationService::RECALCULATE_SCORING, $context->lane);
        $snapshot = $service->priority($request->user(), $context);

        return $this->one($request, $snapshot);
    }

    public function reviewProduct(ReviewProspectingScoreRequest $request, UnitProductRelevanceSnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->review($request, $snapshot, $features, $service);
    }

    public function reviewGood(ReviewProspectingScoreRequest $request, UnitGoodFitSnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->review($request, $snapshot, $features, $service);
    }

    public function reviewPriority(ReviewProspectingScoreRequest $request, UnitProspectPrioritySnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->review($request, $snapshot, $features, $service);
    }

    public function overrideProduct(OverrideProspectingScoreRequest $request, UnitProductRelevanceSnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->override($request, $snapshot, $features, $service);
    }

    public function overrideGood(OverrideProspectingScoreRequest $request, UnitGoodFitSnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->override($request, $snapshot, $features, $service);
    }

    public function overridePriority(OverrideProspectingScoreRequest $request, UnitProspectPrioritySnapshot $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        return $this->override($request, $snapshot, $features, $service);
    }

    private function review(ReviewProspectingScoreRequest $request, Model $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        $features->scoring();
        Gate::authorize('review', $snapshot);

        return $this->one($request, $service->review($snapshot, ScoreReviewStatus::from($request->validated('status')), $request->user()));
    }

    private function override(OverrideProspectingScoreRequest $request, Model $snapshot, ProspectingScoringFeatureGuard $features, ProspectingScoreReviewService $service): JsonResponse
    {
        $features->override();
        Gate::authorize('override', $snapshot);
        $data = $request->validated();

        return $this->one($request, $service->override($snapshot, $data['effective_score'], $data['reason_code'], $data['safe_note'], $data['expires_at'] ?? null, $request->user()));
    }

    private function collection($query): array
    {
        return $query->with('factors')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($snapshot): array => (new ProspectingScoreSnapshotResource($snapshot))->resolve(request()))->all();
    }

    private function one(Request $request, Model $snapshot): JsonResponse
    {
        return response()->json(['data' => (new ProspectingScoreSnapshotResource($snapshot->loadMissing('factors')))->resolve($request)]);
    }
}
