<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Models\OutreachDispatch;

final class OutreachFollowUpCancellationService
{
    public function cancel(OutreachDispatch $dispatch, OutreachFollowUpStatus $status, string $reason): void
    {
        $plan = $dispatch->followUpPlan;
        if (! $plan) {
            return;
        }

        $plan->forceFill([
            'status' => $status,
            'cancellation_reason' => mb_substr($reason, 0, 64),
        ])->save();
        $plan->steps()->whereNotIn('status', [
            OutreachFollowUpStatus::Completed->value,
            OutreachFollowUpStatus::Expired->value,
        ])->update([
            'status' => $status->value,
            'safe_reason_code' => mb_substr($reason, 0, 64),
            'updated_at' => now(),
        ]);
    }
}
