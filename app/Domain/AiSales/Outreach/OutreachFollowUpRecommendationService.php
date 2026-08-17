<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachDispatch;
use App\Models\OutreachFollowUpPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OutreachFollowUpRecommendationService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly OutreachAuthorizationService $authorization,
    ) {}

    public function recommend(OutreachDispatch $dispatch, User $actor): OutreachFollowUpPlan
    {
        $this->features->followupPlanning();
        $dispatch->loadMissing(['unit', 'businessContext']);
        $this->authorization->authorize(
            $actor,
            OutreachAuthorizationService::MANAGE_FOLLOWUPS,
            $dispatch->unit,
            $dispatch->businessContext,
        );

        return DB::transaction(function () use ($dispatch, $actor): OutreachFollowUpPlan {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $existing = OutreachFollowUpPlan::query()->where('outreach_dispatch_id', $dispatch->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            [$status, $reason] = match ($dispatch->state) {
                OutreachDispatchState::Replied => [OutreachFollowUpStatus::CancelledReply, 'reply_received'],
                OutreachDispatchState::HardBounced => [OutreachFollowUpStatus::CancelledBounce, 'hard_bounce'],
                OutreachDispatchState::Complained, OutreachDispatchState::Unsubscribed => [OutreachFollowUpStatus::CancelledSuppression, $dispatch->state->value],
                default => [OutreachFollowUpStatus::ScheduledDisabled, 'new_draft_and_reviews_required'],
            };
            $hash = AiCanonicalJson::hash([
                'dispatch_public_id' => $dispatch->public_id,
                'status' => $status->value,
                'reason' => $reason,
                'max_follow_ups' => 0,
                'policy' => config('ai-sales.policy_versions.outreach_followup', 'stage13-v1'),
            ]);

            return OutreachFollowUpPlan::query()->create([
                'public_id' => (string) Str::uuid(),
                'outreach_dispatch_id' => $dispatch->id,
                'status' => $status,
                'max_follow_ups' => 0,
                'recommendation_code' => $reason,
                'cancellation_reason' => $status === OutreachFollowUpStatus::ScheduledDisabled ? null : $reason,
                'recommendation_hash' => $hash,
                'created_by' => $actor->id,
            ]);
        });
    }
}
