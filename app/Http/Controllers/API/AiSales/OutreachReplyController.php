<?php

namespace App\Http\Controllers\API\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use App\Domain\AiSales\Outreach\OutreachReplyTriageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiSales\ReviewOutreachReplyRequest;
use App\Http\Requests\AiSales\TriageOutreachReplyRequest;
use App\Http\Resources\AiSales\OutreachReplyResource;
use App\Models\OutreachReplyLink;
use App\Models\Unit;
use Illuminate\Support\Facades\Gate;

class OutreachReplyController extends Controller
{
    public function triage(
        TriageOutreachReplyRequest $request,
        Unit $unit,
        OutreachReplyLink $outreachReply,
        OutreachReplyTriageService $service,
    ): OutreachReplyResource {
        abort_unless((int) $outreachReply->dispatch()->value('unit_id') === (int) $unit->id, 404);
        Gate::authorize('review', $outreachReply);

        return new OutreachReplyResource($service->fakeClassify($outreachReply)->load('incomingMessage:id,subject,preview'));
    }

    public function review(
        ReviewOutreachReplyRequest $request,
        Unit $unit,
        OutreachReplyLink $outreachReply,
        OutreachReplyTriageService $service,
    ): OutreachReplyResource {
        abort_unless((int) $outreachReply->dispatch()->value('unit_id') === (int) $unit->id, 404);
        Gate::authorize('review', $outreachReply);
        $reply = $service->review(
            $outreachReply,
            $request->user(),
            OutreachReplyClass::from($request->validated('classification')),
            $request->validated('reason_code'),
        );

        return new OutreachReplyResource($reply->load('incomingMessage:id,subject,preview'));
    }
}
