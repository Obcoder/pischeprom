<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachRevalidationCheckpoint;
use App\Domain\AiSales\Outreach\OutreachAuthorizationService;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Domain\AiSales\Outreach\OutreachFinalRevalidationService;
use App\Domain\AiSales\Outreach\OutreachFollowUpRecommendationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\CancelOutreachDispatchRequest;
use App\Http\Requests\AiSales\PrepareOutreachDispatchRequest;
use App\Http\Requests\AiSales\QueueOutreachDispatchRequest;
use App\Http\Requests\AiSales\StoreOutreachFollowUpPlanRequest;
use App\Http\Resources\AiSales\OutreachDispatchResource;
use App\Http\Resources\AiSales\OutreachEventResource;
use App\Http\Resources\AiSales\OutreachFollowUpPlanResource;
use App\Http\Resources\AiSales\OutreachReplyResource;
use App\Models\MailingEvent;
use App\Models\OutreachDispatch;
use App\Models\OutreachDraft;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OutreachDispatchController extends Controller
{
    public function eligibility(
        Request $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachFinalRevalidationService $service,
        OutreachAuthorizationService $authorization,
    ): JsonResponse {
        $this->assertDraftUnit($unit, $outreachDraft);
        $outreachDraft->loadMissing('unit', 'businessContext');
        $authorization->authorize(
            $request->user(), OutreachAuthorizationService::VIEW_DISPATCH,
            $outreachDraft->unit, $outreachDraft->businessContext,
        );

        return response()->json(['data' => $service->evaluate(
            $outreachDraft,
            $request->user(),
            OutreachRevalidationCheckpoint::EligibilityPreview,
        )->toArray()]);
    }

    public function store(
        PrepareOutreachDispatchRequest $request,
        Unit $unit,
        OutreachDraft $outreachDraft,
        OutreachDispatchService $service,
    ): JsonResponse {
        $this->assertDraftUnit($unit, $outreachDraft);
        $result = $service->prepare($outreachDraft, $request->user(), $request->validated('idempotency_key'));

        return (new OutreachDispatchResource($result->dispatch))
            ->additional(['revalidation' => $result->revalidation->toArray()])
            ->response()
            ->setStatusCode($result->accepted ? 201 : 409);
    }

    public function queue(
        QueueOutreachDispatchRequest $request,
        Unit $unit,
        OutreachDispatch $outreachDispatch,
        OutreachDispatchService $service,
    ): JsonResponse {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('queue', $outreachDispatch);
        $result = $service->queue($outreachDispatch, $request->user());

        return response()->json([
            'data' => (new OutreachDispatchResource($result->dispatch))->resolve($request),
            'revalidation' => $result->revalidation->toArray(),
        ], $result->accepted ? 202 : 409);
    }

    public function cancel(
        CancelOutreachDispatchRequest $request,
        Unit $unit,
        OutreachDispatch $outreachDispatch,
        OutreachDispatchService $service,
    ): OutreachDispatchResource {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('cancel', $outreachDispatch);

        return new OutreachDispatchResource($service->cancel(
            $outreachDispatch,
            $request->user(),
            $request->validated('reason_code'),
        ));
    }

    public function show(Request $request, Unit $unit, OutreachDispatch $outreachDispatch): OutreachDispatchResource
    {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('view', $outreachDispatch);

        return new OutreachDispatchResource($outreachDispatch);
    }

    public function events(Request $request, Unit $unit, OutreachDispatch $outreachDispatch)
    {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('viewEvents', $outreachDispatch);
        $events = MailingEvent::query()
            ->select([
                'id', 'provider_event_id', 'normalized_event_type', 'normalized_status',
                'event_time', 'verified_at', 'processed_at', 'safe_error_code', 'safe_summary',
            ])
            ->where('sending_id', $outreachDispatch->sending_id)
            ->orderBy('event_time')->orderBy('id')->limit(100)->get();

        return OutreachEventResource::collection($events);
    }

    public function reply(Request $request, Unit $unit, OutreachDispatch $outreachDispatch)
    {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('viewReplies', $outreachDispatch);
        $replies = $outreachDispatch->replies()->with('incomingMessage:id,subject,preview')->latest('id')->limit(20)->get();

        return OutreachReplyResource::collection($replies);
    }

    public function followUpPlan(
        StoreOutreachFollowUpPlanRequest $request,
        Unit $unit,
        OutreachDispatch $outreachDispatch,
        OutreachFollowUpRecommendationService $service,
    ): OutreachFollowUpPlanResource {
        $this->assertDispatchUnit($unit, $outreachDispatch);
        Gate::authorize('manageFollowups', $outreachDispatch);

        return new OutreachFollowUpPlanResource($service->recommend($outreachDispatch, $request->user())->load('steps'));
    }

    private function assertDraftUnit(Unit $unit, OutreachDraft $draft): void
    {
        abort_unless((int) $draft->unit_id === (int) $unit->id, 404);
    }

    private function assertDispatchUnit(Unit $unit, OutreachDispatch $dispatch): void
    {
        abort_unless((int) $dispatch->unit_id === (int) $unit->id, 404);
    }
}
