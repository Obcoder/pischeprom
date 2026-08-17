<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Models\OutreachDispatch;
use Illuminate\Support\Facades\DB;

final class OutreachUnsubscribeService
{
    public function __construct(
        private readonly CommunicationSuppressionService $suppressions,
        private readonly OutreachDispatchStateMachine $states,
    ) {}

    public function find(string $token): ?OutreachDispatch
    {
        if (preg_match('/\A[A-Za-z0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        return OutreachDispatch::query()->where('unsubscribe_token_hash', hash('sha256', $token))->first();
    }

    public function unsubscribe(string $token): ?OutreachDispatch
    {
        $resolved = $this->find($token);
        if (! $resolved) {
            return null;
        }

        return DB::transaction(function () use ($resolved): OutreachDispatch {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($resolved->id);
            $this->states->transition($dispatch, OutreachDispatchState::Unsubscribed, 'recipient_unsubscribed');
            $this->suppressions->createSystemEndpointSuppression(
                $dispatch,
                CommunicationSuppressionReason::Unsubscribed,
                'outreach_unsubscribe',
                'outreach-dispatch:'.$dispatch->public_id,
            );
            if ($dispatch->followUpPlan) {
                $dispatch->followUpPlan->forceFill([
                    'status' => OutreachFollowUpStatus::CancelledSuppression,
                    'cancellation_reason' => 'unsubscribed',
                ])->save();
            }

            return $dispatch->fresh();
        });
    }
}
