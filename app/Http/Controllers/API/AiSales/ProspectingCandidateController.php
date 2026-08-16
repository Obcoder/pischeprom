<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ProspectingActionRequest;
use App\Http\Requests\AiSales\RejectProspectingCandidateRequest;
use App\Http\Requests\AiSales\ResolveProspectingCandidateRequest;
use App\Http\Resources\AiSales\ProspectingCandidateResource;
use App\Models\ProspectingCandidate;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProspectingCandidateController extends Controller
{
    public function index(Request $request, ProspectingFeatureGuard $features, ProspectingAuthorizationService $authorization): JsonResponse
    {
        $features->dossier();
        Gate::authorize('viewAny', ProspectingCandidate::class);
        $filters = validator($request->query(), [
            'status' => ['nullable', Rule::enum(ProspectingCandidateStatus::class)],
            'job_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();
        $lanes = collect([BusinessLane::Sales, BusinessLane::Procurement])
            ->filter(fn ($lane) => $authorization->can($request->user(), ProspectingAuthorizationService::VIEW, $lane))->pluck('value');
        $candidates = ProspectingCandidate::query()->whereIn('lane', $lanes)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['job_id'] ?? null, fn ($query, $id) => $query->whereHas('job', fn ($job) => $job->where('public_id', $id)))
            ->with([
                'job:id,public_id,product_mapping_state,product_mapping_reason_code', 'job.goods:id,name', 'sources',
                'products.product' => fn ($query) => $query->without(['category', 'manufacturers'])->select(['products.id', 'products.rus', 'products.eng']),
                'channels:id,prospecting_candidate_id,channel_kind,contact_role,data_classification,communication_state',
                'unitMatches.unit' => fn ($query) => $query->without(['fields', 'labels', 'telephones', 'uris'])->select(['units.id', 'units.name']),
                'resolvedUnit' => fn ($query) => $query->without(['fields', 'labels', 'telephones', 'uris'])->select(['units.id', 'units.name']),
            ])
            ->latest('id')->paginate($filters['per_page'] ?? 25);

        return response()->json([
            'data' => ProspectingCandidateResource::collection($candidates->getCollection())->resolve($request),
            'meta' => ['current_page' => $candidates->currentPage(), 'last_page' => $candidates->lastPage(), 'total' => $candidates->total()],
        ]);
    }

    public function show(Request $request, ProspectingCandidate $prospectingCandidate, ProspectingFeatureGuard $features): JsonResponse
    {
        $features->dossier();
        Gate::authorize('view', $prospectingCandidate);
        $candidate = $prospectingCandidate->load([
            'job:id,public_id,product_mapping_state,product_mapping_reason_code', 'job.goods:id,name', 'sources',
            'products.product' => fn ($query) => $query->without(['category', 'manufacturers'])->select(['products.id', 'products.rus', 'products.eng']),
            'channels:id,prospecting_candidate_id,channel_kind,contact_role,data_classification,communication_state',
            'resolvedUnit' => fn ($query) => $query->without(['fields', 'labels', 'telephones', 'uris'])->select(['units.id', 'units.name']),
            'unitMatches.unit' => fn ($query) => $query->without(['fields', 'labels', 'telephones', 'uris'])->select(['units.id', 'units.name']),
        ]);

        return response()->json(['data' => (new ProspectingCandidateResource($candidate))->resolve($request)]);
    }

    public function evaluate(ProspectingActionRequest $request, ProspectingCandidate $prospectingCandidate, ResolveProspectingCandidate $service): JsonResponse
    {
        Gate::authorize('review', $prospectingCandidate);
        $decision = $service->evaluate($prospectingCandidate, $request->user());

        return response()->json(['data' => $decision->safeArray()]);
    }

    public function resolveExisting(ResolveProspectingCandidateRequest $request, ProspectingCandidate $prospectingCandidate, ResolveProspectingCandidate $service): JsonResponse
    {
        Gate::authorize('resolve', $prospectingCandidate);
        $unit = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->findOrFail($request->integer('unit_id'));
        $resolved = $service->enrichExisting($prospectingCandidate, $unit, $request->user());

        return response()->json(['data' => ['candidate_id' => $prospectingCandidate->public_id, 'unit' => ['id' => $resolved->id, 'name' => $resolved->name], 'entity_mutated' => false]]);
    }

    public function createUnit(ProspectingActionRequest $request, ProspectingCandidate $prospectingCandidate, ResolveProspectingCandidate $service): JsonResponse
    {
        Gate::authorize('resolve', $prospectingCandidate);
        $unit = $service->createNewUnit($prospectingCandidate, $request->user());

        return response()->json(['data' => ['candidate_id' => $prospectingCandidate->public_id, 'unit' => ['id' => $unit->id, 'name' => $unit->name], 'entity_created' => false]], 201);
    }

    public function reject(RejectProspectingCandidateRequest $request, ProspectingCandidate $prospectingCandidate, ResolveProspectingCandidate $service): JsonResponse
    {
        Gate::authorize('review', $prospectingCandidate);
        $candidate = $service->reject($prospectingCandidate, $request->user(), $request->validated('reason_code'));

        return response()->json(['data' => (new ProspectingCandidateResource($candidate))->resolve($request)]);
    }
}
