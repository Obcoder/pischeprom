<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Outreach\Enums\OutreachDispatchState;
use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use App\Domain\AiSales\Services\UnitDossierAuditLogger;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\MailMessage;
use App\Models\OutreachDispatch;
use App\Models\OutreachReplyLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OutreachReplyCorrelationService
{
    public function __construct(
        private readonly OutreachFeatureGuard $features,
        private readonly OutreachDispatchStateMachine $states,
        private readonly OutreachFollowUpCancellationService $followUps,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function safeCorrelate(MailMessage $incoming): ?OutreachReplyLink
    {
        if (! $this->features->replyCorrelationEnabled() || $incoming->direction !== 'incoming') {
            return null;
        }

        try {
            return $this->correlate($incoming);
        } catch (\Throwable) {
            return null;
        }
    }

    public function correlate(MailMessage $incoming): ?OutreachReplyLink
    {
        if (! $this->features->replyCorrelationEnabled() || $incoming->direction !== 'incoming') {
            return null;
        }

        $ids = $this->threadIds($incoming);
        if ($ids === []) {
            return null;
        }

        $outgoing = MailMessage::query()
            ->where('direction', 'outgoing')
            ->whereIn('message_id', $ids)
            ->whereHas('outreachDispatch')
            ->with('outreachDispatch.contactLink.email', 'outreachDispatch.unit', 'outreachDispatch.businessContext')
            ->first();
        if (! $outgoing?->outreachDispatch?->contactLink?->email) {
            return null;
        }

        $expected = Str::lower(trim($outgoing->outreachDispatch->contactLink->email->address));
        $actual = Str::lower(trim((string) $incoming->from_address));
        if ($actual === '' || ! hash_equals($expected, $actual)) {
            return null;
        }

        return DB::transaction(function () use ($incoming, $outgoing, $ids): OutreachReplyLink {
            $dispatch = OutreachDispatch::query()->lockForUpdate()->findOrFail($outgoing->outreachDispatch->id);
            $existing = OutreachReplyLink::query()->where('incoming_mail_message_id', $incoming->id)->first();
            if ($existing) {
                return $existing;
            }

            $matchedId = collect($ids)->first(fn (string $id): bool => hash_equals((string) $outgoing->message_id, $id));
            $link = OutreachReplyLink::query()->create([
                'public_id' => (string) Str::uuid(),
                'outreach_dispatch_id' => $dispatch->id,
                'incoming_mail_message_id' => $incoming->id,
                'correlation_method' => 'rfc_thread_exact',
                'correlation_hash' => AiCanonicalJson::hash([
                    'dispatch_public_id' => $dispatch->public_id,
                    'incoming_mail_message_id' => $incoming->id,
                    'matched_message_id_hash' => hash('sha256', (string) $matchedId),
                ]),
                'triage_profile' => (string) config('ai-sales.outreach.reply_triage_profile', 'outreach_reply_triage.v1'),
                'triage_status' => 'review_required',
                'safe_reason_code' => 'human_reply_requires_review',
                'created_at' => now(),
            ]);
            if (! $incoming->reply_to_mail_message_id) {
                $incoming->forceFill(['reply_to_mail_message_id' => $outgoing->id])->save();
            }
            $this->states->transition($dispatch, OutreachDispatchState::Replied, 'exact_human_reply_correlated');
            $this->followUps->cancel($dispatch, OutreachFollowUpStatus::CancelledReply, 'human_reply');
            $this->audit->record(
                $dispatch->unit,
                'outreach.reply_correlated',
                'Exact inbound reply linked; human review is required and follow-up is stopped.',
                context: $dispatch->businessContext,
                subjectType: 'outreach_reply',
                subjectId: $link->id,
                metadata: ['dispatch_id' => $dispatch->id, 'mail_message_id' => $incoming->id, 'review_required' => true],
            );

            return $link;
        });
    }

    /** @return list<string> */
    private function threadIds(MailMessage $incoming): array
    {
        $candidates = [];
        foreach ([(string) $incoming->in_reply_to, (string) $incoming->references] as $value) {
            preg_match_all('/<[^<>\s]{1,180}@[^<>\s]{1,180}>/', $value, $matches);
            foreach ($matches[0] ?? [] as $match) {
                $candidates[] = mb_substr($match, 0, 383);
            }
        }

        return array_slice(array_values(array_unique($candidates)), 0, 20);
    }
}
