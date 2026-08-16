<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ProspectingActionRequest;
use App\Http\Requests\AiSales\StoreProspectingSearchJobRequest;
use App\Http\Requests\AiSales\UpdateProspectingSearchJobRequest;
use App\Http\Resources\AiSales\ProspectingSearchJobResource;
use App\Models\ProspectingSearchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProspectingSearchJobController extends Controller
{
    public function index(Request $request, ProspectingFeatureGuard $features, ProspectingAuthorizationService $authorization): JsonResponse
    {
        $features->jobs();
        Gate::authorize('viewAny', ProspectingSearchJob::class);
        $filters = validator($request->query(), [
            'status' => ['nullable', Rule::enum(ProspectingJobStatus::class)],
            'purpose' => ['nullable', Rule::enum(ProspectingPurpose::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $lanes = collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->filter(fn ($lane) => $authorization->can($request->user(), ProspectingAuthorizationService::VIEW, $lane))
            ->pluck('value');
        $jobs = ProspectingSearchJob::query()->whereIn('lane', $lanes)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['purpose'] ?? null, fn ($query, $purpose) => $query->where('purpose', $purpose))
            ->with(['owner:id,name', 'reviewer:id,name', 'primaryGood:id,name'])
            ->latest('id')->paginate($filters['per_page'] ?? 25);

        return response()->json([
            'data' => ProspectingSearchJobResource::collection($jobs->getCollection())->resolve($request),
            'meta' => ['current_page' => $jobs->currentPage(), 'last_page' => $jobs->lastPage(), 'total' => $jobs->total()],
        ]);
    }

    public function store(StoreProspectingSearchJobRequest $request, ProspectingSearchJobService $service, ProspectingAuthorizationService $authorization): JsonResponse
    {
        Gate::authorize('create', ProspectingSearchJob::class);
        $purpose = ProspectingPurpose::from($request->validated('purpose'));
        $authorization->authorize($request->user(), ProspectingAuthorizationService::MANAGE_JOBS, $purpose->lane());
        $job = $service->createDraft($request->validated(), $request->user());

        return response()->json(['data' => (new ProspectingSearchJobResource($job))->resolve($request)], 201);
    }

    public function show(Request $request, ProspectingSearchJob $prospectingSearchJob, ProspectingFeatureGuard $features): JsonResponse
    {
        $features->jobs();
        Gate::authorize('view', $prospectingSearchJob);

        return response()->json(['data' => (new ProspectingSearchJobResource(
            $prospectingSearchJob->load(['owner:id,name', 'reviewer:id,name', 'primaryGood:id,name', 'goods:id,name'])
        ))->resolve($request)]);
    }

    public function update(
        UpdateProspectingSearchJobRequest $request,
        ProspectingSearchJob $prospectingSearchJob,
        ProspectingSearchJobService $service,
        ProspectingAuthorizationService $authorization,
    ): JsonResponse {
        Gate::authorize('update', $prospectingSearchJob);
        $targetPurpose = ProspectingPurpose::from($request->validated('purpose', $prospectingSearchJob->purpose->value));
        $authorization->authorize($request->user(), ProspectingAuthorizationService::MANAGE_JOBS, $targetPurpose->lane());
        $job = $service->updateDraft($prospectingSearchJob, $request->validated(), $request->user());

        return response()->json(['data' => (new ProspectingSearchJobResource($job))->resolve($request)]);
    }

    public function submit(ProspectingActionRequest $request, ProspectingSearchJob $prospectingSearchJob, ProspectingSearchJobService $service): JsonResponse
    {
        Gate::authorize('update', $prospectingSearchJob);

        return $this->jobResponse($request, $service->submit($prospectingSearchJob, $request->user()));
    }

    public function approve(ProspectingActionRequest $request, ProspectingSearchJob $prospectingSearchJob, ProspectingSearchJobService $service): JsonResponse
    {
        Gate::authorize('review', $prospectingSearchJob);

        return $this->jobResponse($request, $service->approve($prospectingSearchJob, $request->user()));
    }

    public function cancel(ProspectingActionRequest $request, ProspectingSearchJob $prospectingSearchJob, ProspectingSearchJobService $service): JsonResponse
    {
        Gate::authorize('update', $prospectingSearchJob);

        return $this->jobResponse($request, $service->cancel($prospectingSearchJob, $request->user()));
    }

    public function archive(ProspectingActionRequest $request, ProspectingSearchJob $prospectingSearchJob, ProspectingSearchJobService $service): JsonResponse
    {
        Gate::authorize('review', $prospectingSearchJob);

        return $this->jobResponse($request, $service->archive($prospectingSearchJob, $request->user()));
    }

    private function jobResponse(Request $request, ProspectingSearchJob $job): JsonResponse
    {
        return response()->json(['data' => (new ProspectingSearchJobResource($job))->resolve($request)]);
    }
}
