<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\FindBuyers\BuildFindBuyersQueryPlan;
use App\Domain\AiSales\FindBuyers\CancelFindBuyersJob;
use App\Domain\AiSales\FindBuyers\FindBuyersAuthorizationService;
use App\Domain\AiSales\FindBuyers\FindBuyersDashboardQuery;
use App\Domain\AiSales\FindBuyers\FindBuyersDraftOrchestrator;
use App\Domain\AiSales\FindBuyers\FindBuyersFeatureGuard;
use App\Domain\AiSales\FindBuyers\FindBuyersGeographyService;
use App\Domain\AiSales\FindBuyers\FindBuyersLaunchContextResolver;
use App\Domain\AiSales\FindBuyers\FindBuyersProgressQuery;
use App\Domain\AiSales\FindBuyers\SubmitFindBuyersPlanForReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\FindBuyersActionRequest;
use App\Http\Requests\AiSales\FindBuyersDashboardRequest;
use App\Http\Requests\AiSales\FindBuyersGeographyRequest;
use App\Http\Requests\AiSales\FindBuyersLaunchContextRequest;
use App\Http\Requests\AiSales\FindBuyersProgressRequest;
use App\Http\Requests\AiSales\StoreFindBuyersDraftRequest;
use App\Http\Requests\AiSales\UpdateFindBuyersDraftRequest;
use App\Http\Resources\AiSales\FindBuyersLaunchContextResource;
use App\Http\Resources\AiSales\FindBuyersProgressResource;
use App\Http\Resources\AiSales\ProspectingSearchJobResource;
use App\Models\ProspectingSearchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FindBuyersController extends Controller
{
    public function launchContext(
        FindBuyersLaunchContextRequest $request,
        FindBuyersLaunchContextResolver $resolver,
    ): JsonResponse {
        $input = $request->validated();
        $context = $resolver->resolve(
            $request->user(),
            $input['source_type'],
            (int) $input['source_id'],
            isset($input['selected_product_id']) ? (int) $input['selected_product_id'] : null,
        );

        return response()->json(['data' => (new FindBuyersLaunchContextResource($context))->resolve($request)]);
    }

    public function geography(
        FindBuyersGeographyRequest $request,
        FindBuyersFeatureGuard $features,
        FindBuyersAuthorizationService $authorization,
        FindBuyersGeographyService $geography,
    ): JsonResponse {
        $features->ui();
        $authorization->authorizeLaunch($request->user());
        $input = $request->validated();
        $selection = $geography->validate(
            isset($input['country_id']) ? (int) $input['country_id'] : null,
            isset($input['region_id']) ? (int) $input['region_id'] : null,
            null,
        );

        return response()->json(['data' => $geography->options($selection['country_id'], $selection['region_id'])]);
    }

    public function store(
        StoreFindBuyersDraftRequest $request,
        FindBuyersDraftOrchestrator $orchestrator,
    ): JsonResponse {
        Gate::authorize('create', ProspectingSearchJob::class);
        $result = $orchestrator->create($request->validated(), $request->user());

        return response()->json([
            'data' => (new ProspectingSearchJobResource($result->job))->resolve($request),
            'idempotent_replay' => ! $result->created,
            'unit_created' => false,
            'entity_created' => false,
            'entity_linked' => false,
        ], $result->created ? 201 : 200);
    }

    public function update(
        UpdateFindBuyersDraftRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        FindBuyersDraftOrchestrator $orchestrator,
    ): JsonResponse {
        Gate::authorize('update', $prospectingSearchJob);
        $job = $orchestrator->update($prospectingSearchJob, $request->validated(), $request->user());

        return $this->jobResponse($request, $job);
    }

    public function plan(
        FindBuyersActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        BuildFindBuyersQueryPlan $service,
    ): JsonResponse {
        Gate::authorize('update', $prospectingSearchJob);
        $queries = $service->handle($prospectingSearchJob, $request->user());

        return response()->json(['data' => $queries->map(fn ($query): array => [
            'id' => (int) $query->id,
            'sequence' => (int) $query->sequence,
            'template_code' => $query->template_code,
            'template_version' => $query->template_version,
            'plan_status' => $query->plan_status,
            'query' => $query->safe_display_query,
            'language' => $query->language,
            'geography' => $query->geography,
            'industry_intent' => $query->industry_intent,
        ])->all()], 201);
    }

    public function submit(
        FindBuyersActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        SubmitFindBuyersPlanForReview $service,
    ): JsonResponse {
        Gate::authorize('update', $prospectingSearchJob);

        return $this->jobResponse($request, $service->handle($prospectingSearchJob, $request->user()));
    }

    public function cancel(
        FindBuyersActionRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        CancelFindBuyersJob $service,
    ): JsonResponse {
        Gate::authorize('update', $prospectingSearchJob);

        return $this->jobResponse($request, $service->handle($prospectingSearchJob, $request->user()));
    }

    public function progress(
        FindBuyersProgressRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        FindBuyersProgressQuery $query,
    ): JsonResponse {
        Gate::authorize('view', $prospectingSearchJob);
        $progress = $query->get($prospectingSearchJob, $request->user());

        return response()->json(['data' => (new FindBuyersProgressResource($progress))->resolve($request)]);
    }

    public function dashboard(
        FindBuyersDashboardRequest $request,
        FindBuyersDashboardQuery $query,
    ): JsonResponse {
        Gate::authorize('viewAny', ProspectingSearchJob::class);
        $validated = $request->validated();

        return response()->json(['data' => $query->get($request->user(), (int) ($validated['limit'] ?? 25))]);
    }

    private function jobResponse(Request $request, ProspectingSearchJob $job): JsonResponse
    {
        return response()->json(['data' => (new ProspectingSearchJobResource($job))->resolve($request)]);
    }
}
