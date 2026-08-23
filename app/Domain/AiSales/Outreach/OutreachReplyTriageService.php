<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\CommunicationSuppressionReason;
use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use App\Domain\AiSales\Outreach\Enums\OutreachReplyTriageStatus;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\OutreachReplyLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class OutreachReplyTriageService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly OutreachAuthorizationService $authorization,
        private readonly FakeOutreachReplyTriageProvider $provider,
        private readonly CommunicationSuppressionService $suppressions,
    ) {}

    public function fakeClassify(OutreachReplyLink $reply): OutreachReplyLink
    {
        $this->features->replyTriage();
        $reply->loadMissing('incomingMessage');
        $classification = $this->provider->classify($reply->incomingMessage);

        return $reply->forceFill([
            'triage_status' => OutreachReplyTriageStatus::FakeClassified,
            'triage_class' => $classification,
            'triage_hash' => AiCanonicalJson::hash([
                'profile' => config('ai-sales.outreach.reply_triage_profile'),
                'mail_message_id' => $reply->incoming_mail_message_id,
                'subject_hash' => hash('sha256', (string) $reply->incomingMessage->subject),
                'preview_hash' => hash('sha256', (string) $reply->incomingMessage->preview),
                'classification' => $classification->value,
            ]),
            'safe_reason_code' => $classification === OutreachReplyClass::Unknown
                ? 'unknown_reply_requires_review'
                : 'fake_classification_requires_review',
        ])->save() ? $reply->fresh() : $reply;
    }

    public function review(OutreachReplyLink $reply, User $actor, OutreachReplyClass $classification, string $reasonCode): OutreachReplyLink
    {
        $reply->loadMissing('dispatch.unit', 'dispatch.businessContext', 'dispatch.contactLink.email');
        $this->authorization->authorize(
            $actor,
            OutreachAuthorizationService::REVIEW_REPLIES,
            $reply->dispatch->unit,
            $reply->dispatch->businessContext,
        );

        return DB::transaction(function () use ($reply, $actor, $classification, $reasonCode): OutreachReplyLink {
            $reply = OutreachReplyLink::query()->lockForUpdate()->findOrFail($reply->id);
            $reply->forceFill([
                'triage_status' => OutreachReplyTriageStatus::Reviewed,
                'triage_class' => $classification,
                'triage_hash' => AiCanonicalJson::hash([
                    'reply_public_id' => $reply->public_id,
                    'classification' => $classification->value,
                    'reviewer_id' => $actor->id,
                    'reason_code' => $reasonCode,
                ]),
                'safe_reason_code' => mb_substr($reasonCode, 0, 64),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $suppressionReason = match ($classification) {
                OutreachReplyClass::UnsubscribeRequest => CommunicationSuppressionReason::Unsubscribed,
                OutreachReplyClass::NotInterested, OutreachReplyClass::WrongContact => CommunicationSuppressionReason::DoNotContact,
                default => null,
            };
            if ($suppressionReason) {
                $this->suppressions->createSystemEndpointSuppression(
                    $reply->dispatch,
                    $suppressionReason,
                    'reviewed_outreach_reply',
                    'outreach-reply:'.$reply->public_id,
                );
            }

            return $reply->fresh();
        });
    }
}
