<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignAuthorizationService;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignMetrics;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignService;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ClientAcquisitionCampaignActionRequest;
use App\Http\Requests\AiSales\RunClientAcquisitionCampaignRequest;
use App\Http\Requests\AiSales\StoreClientAcquisitionCampaignRequest;
use App\Http\Requests\AiSales\UpdateClientAcquisitionCampaignRequest;
use App\Http\Resources\AiSales\ClientAcquisitionCampaignResource;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\ClientAcquisitionCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientAcquisitionCampaignController extends Controller
{
    public function index(Request $request, ClientAcquisitionCampaignAuthorizationService $authorization): JsonResponse
    {
        Gate::authorize('viewAny', ClientAcquisitionCampaign::class);
        $query = ClientAcquisitionCampaign::query()->with($this->relations())->latest('id');
        if (! $authorization->isAdmin($request->user())) {
            $userId = $request->user()->id;
            $query->where(fn ($scope) => $scope->where('owner_user_id', $userId)
                ->orWhere('reviewer_user_id', $userId)->orWhere('approved_by', $userId));
        }

        return response()->json(['data' => ClientAcquisitionCampaignResource::collection($query->limit(100)->get())->resolve($request)]);
    }

    public function store(StoreClientAcquisitionCampaignRequest $request, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('create', ClientAcquisitionCampaign::class);
        $campaign = $service->create($request->validated(), $request->user());

        return response()->json(['data' => (new ClientAcquisitionCampaignResource($campaign))->resolve($request)], 201);
    }

    public function show(Request $request, ClientAcquisitionCampaign $clientAcquisitionCampaign): JsonResponse
    {
        Gate::authorize('view', $clientAcquisitionCampaign);

        return $this->campaign($request, $clientAcquisitionCampaign);
    }

    public function update(UpdateClientAcquisitionCampaignRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('update', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->update($clientAcquisitionCampaign, $request->validated(), $request->user()));
    }

    public function submit(ClientAcquisitionCampaignActionRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('update', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->submit($clientAcquisitionCampaign, $request->user()));
    }

    public function approve(ClientAcquisitionCampaignActionRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('review', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->approve($clientAcquisitionCampaign, $request->user()));
    }

    public function pause(ClientAcquisitionCampaignActionRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('operate', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->pause($clientAcquisitionCampaign, $request->user()));
    }

    public function resume(ClientAcquisitionCampaignActionRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('operate', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->resume($clientAcquisitionCampaign, $request->user()));
    }

    public function cancel(ClientAcquisitionCampaignActionRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignService $service): JsonResponse
    {
        Gate::authorize('operate', $clientAcquisitionCampaign);

        return $this->campaign($request, $service->cancel($clientAcquisitionCampaign, $request->user()));
    }

    public function run(RunClientAcquisitionCampaignRequest $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, StartClientAcquisitionCampaignRun $service): JsonResponse
    {
        Gate::authorize('operate', $clientAcquisitionCampaign);
        $run = $service->handle($clientAcquisitionCampaign, $request->user(), $request->validated('idempotency_token'));
        ExecuteClientAcquisitionCampaignRunJob::dispatch($run->id, $request->user()->id);

        return response()->json(['data' => [
            'campaign_id' => $clientAcquisitionCampaign->public_id,
            'run_id' => $run->public_id,
            'status' => $run->fresh()->status->value,
            'live_execution_available' => false,
            'email_dispatch_available' => false,
        ]], 202);
    }

    public function progress(Request $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, ClientAcquisitionCampaignMetrics $metrics): JsonResponse
    {
        Gate::authorize('viewMetrics', $clientAcquisitionCampaign);

        return response()->json(['data' => $metrics->get($clientAcquisitionCampaign, $request->user())]);
    }

    public function reviewQueue(Request $request, ClientAcquisitionCampaign $clientAcquisitionCampaign, CampaignReviewQueue $queue): JsonResponse
    {
        Gate::authorize('view', $clientAcquisitionCampaign);

        return response()->json(['data' => $queue->forCampaign($clientAcquisitionCampaign, $request->user(), (int) $request->integer('limit', 100))]);
    }

    private function campaign(Request $request, ClientAcquisitionCampaign $campaign): JsonResponse
    {
        return response()->json(['data' => (new ClientAcquisitionCampaignResource($campaign->load($this->relations())))->resolve($request)]);
    }

    private function relations(): array
    {
        return [
            'owner:id,name', 'reviewer:id,name',
            'products' => fn ($query) => $query->without(['category', 'manufacturers'])->select(['products.id', 'products.rus', 'products.eng']),
            'runLinks.run',
        ];
    }
}
