<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Domain\AiSales\Outreach\OutreachFollowUpRecommendationService;
use App\Domain\AiSales\Outreach\OutreachNormalizedEventService;
use App\Domain\AiSales\Outreach\OutreachReplyCorrelationService;
use App\Domain\AiSales\Outreach\OutreachReplyTriageService;
use App\Models\CommunicationPermission;
use App\Models\MailingEvent;
use App\Models\MailMessage;

class Stage13EventsRepliesAndFollowUpsTest extends Stage13TestCase
{
    public function test_normalized_events_apply_precedence_create_suppression_and_never_grant_permission(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, , , , , , , $draft] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        config()->set([
            'ai-sales.outreach.event_ingestion_enabled' => true,
            'ai-sales.outreach.followup_planning_enabled' => true,
        ]);
        $drafts = app(OutreachDraftService::class);
        $revision = $drafts->revise($draft->fresh(), $actor, $draft->currentRevision()->structured_content);
        foreach (OutreachReviewType::cases() as $type) {
            $drafts->review($draft->fresh(), $revision, $actor, $type, OutreachReviewDecision::Approved, 'human_review', null);
        }
        $secondDispatch = $this->prepareDispatch($fixture);
        $secondPlan = app(OutreachFollowUpRecommendationService::class)->recommend($secondDispatch, $actor);
        $initialPermissionCount = CommunicationPermission::query()->count();

        $hardBounce = $this->event($dispatch->sending_id, 'hard_bounced', 1);
        app(OutreachNormalizedEventService::class)->apply($hardBounce);
        $delivered = $this->event($dispatch->sending_id, 'delivered', 2);
        app(OutreachNormalizedEventService::class)->apply($delivered);
        $opened = $this->event($dispatch->sending_id, 'opened', 3);
        app(OutreachNormalizedEventService::class)->apply($opened);

        $this->assertSame('hard_bounced', $dispatch->fresh()->state->value);
        $this->assertDatabaseHas('communication_suppressions', [
            'unit_business_context_id' => $dispatch->unit_business_context_id,
            'reason' => 'hard_bounce',
        ]);
        $this->assertSame($initialPermissionCount, CommunicationPermission::query()->count());
        $this->assertSame(1, $dispatch->sending->fresh()->opens_count);
        $this->assertSame('hard_bounced', $dispatch->sending->fresh()->status);
        $this->assertSame('blocked', $secondDispatch->fresh()->state->value);
        $this->assertSame('cancelled_bounce', $secondPlan->fresh()->status->value);
        $this->assertNull($opened->payload);
    }

    public function test_complaint_outweighs_unsubscribe_and_duplicate_event_is_idempotent(): void
    {
        $fixture = $this->approvedOutreachFixture();
        $dispatch = $this->prepareDispatch($fixture);
        config()->set('ai-sales.outreach.event_ingestion_enabled', true);
        $service = app(OutreachNormalizedEventService::class);

        $service->apply($this->event($dispatch->sending_id, 'unsubscribed', 1));
        $complaint = $this->event($dispatch->sending_id, 'spam', 2);
        $service->apply($complaint);
        $service->apply($complaint);

        $this->assertSame('complained', $dispatch->fresh()->state->value);
        $this->assertSame('complained', $dispatch->sending->fresh()->status);
        $this->assertDatabaseCount('communication_suppressions', 2);
    }

    public function test_exact_reply_correlation_copies_no_raw_body_and_stops_followup(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        config()->set([
            'ai-sales.outreach.reply_correlation_enabled' => true,
            'ai-sales.outreach.followup_planning_enabled' => true,
        ]);
        $plan = app(OutreachFollowUpRecommendationService::class)->recommend($dispatch, $actor);
        $incoming = MailMessage::query()->create([
            'mailbox' => 'owner@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'message_id' => '<reply@example.test>', 'in_reply_to' => $dispatch->mailMessage->message_id,
            'references' => $dispatch->mailMessage->message_id,
            'subject' => 'Re: '.$dispatch->mailMessage->subject,
            'message_date' => now(), 'from_address' => $dispatch->contactLink->email->address,
            'to' => [['address' => 'owner@example.test']], 'cc' => [],
            'preview' => 'Interested in a sample', 'text' => 'FULL RAW REPLY MUST STAY HERE',
            'html' => '<p>FULL RAW REPLY MUST STAY HERE</p>', 'body_loaded_at' => now(),
            'has_attachments' => false,
        ]);

        $link = app(OutreachReplyCorrelationService::class)->correlate($incoming);

        $this->assertNotNull($link);
        $this->assertSame('replied', $dispatch->fresh()->state->value);
        $this->assertSame('cancelled_reply', $plan->fresh()->status->value);
        $stored = \Illuminate\Support\Facades\DB::table('outreach_reply_links')->where('id', $link->id)->first();
        $encoded = json_encode($stored, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('FULL RAW REPLY', $encoded);
        $this->assertDatabaseHas('unit_dossier_audit_events', ['event_type' => 'outreach.reply_correlated']);
    }

    public function test_wrong_thread_or_sender_does_not_correlate(): void
    {
        $fixture = $this->approvedOutreachFixture();
        $dispatch = $this->prepareDispatch($fixture);
        config()->set('ai-sales.outreach.reply_correlation_enabled', true);
        $incoming = MailMessage::query()->create([
            'mailbox' => 'owner@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'message_id' => '<wrong@example.test>', 'in_reply_to' => $dispatch->mailMessage->message_id,
            'subject' => 'Wrong sender', 'from_address' => 'other@example.test',
            'message_date' => now(), 'to' => [], 'cc' => [], 'has_attachments' => false,
        ]);

        $this->assertNull(app(OutreachReplyCorrelationService::class)->correlate($incoming));
        $this->assertDatabaseCount('outreach_reply_links', 0);
    }

    public function test_exact_reply_resolves_ambiguous_acceptance_and_stops_new_work(): void
    {
        $fixture = $this->approvedOutreachFixture();
        $dispatch = $this->prepareDispatch($fixture);
        $dispatch->forceFill(['state' => 'ambiguous_acceptance', 'ambiguous_acceptance_at' => now()])->save();
        config()->set('ai-sales.outreach.reply_correlation_enabled', true);
        $incoming = MailMessage::query()->create([
            'mailbox' => 'owner@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'message_id' => '<ambiguous-reply@example.test>', 'in_reply_to' => $dispatch->mailMessage->message_id,
            'subject' => 'Re: accepted message', 'from_address' => $dispatch->contactLink->email->address,
            'message_date' => now(), 'to' => [], 'cc' => [], 'preview' => 'Received', 'has_attachments' => false,
        ]);

        $link = app(OutreachReplyCorrelationService::class)->correlate($incoming);

        $this->assertNotNull($link);
        $this->assertSame('replied', $dispatch->fresh()->state->value);
    }

    public function test_fake_triage_cannot_reply_and_reviewed_unsubscribe_creates_suppression(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        config()->set([
            'ai-sales.outreach.reply_correlation_enabled' => true,
            'ai-sales.outreach.reply_triage_enabled' => true,
            'ai-sales.outreach.transport_mode' => 'fake_only',
        ]);
        $incoming = MailMessage::query()->create([
            'mailbox' => 'owner@example.test', 'folder' => 'INBOX', 'direction' => 'incoming',
            'message_id' => '<unsubscribe@example.test>', 'in_reply_to' => $dispatch->mailMessage->message_id,
            'subject' => 'Please unsubscribe', 'from_address' => $dispatch->contactLink->email->address,
            'message_date' => now(), 'to' => [], 'cc' => [], 'preview' => 'unsubscribe', 'has_attachments' => false,
        ]);
        $link = app(OutreachReplyCorrelationService::class)->correlate($incoming);
        $service = app(OutreachReplyTriageService::class);

        $classified = $service->fakeClassify($link);
        $reviewed = $service->review($classified, $actor, OutreachReplyClass::UnsubscribeRequest, 'human_confirmed_unsubscribe');

        $this->assertSame('unsubscribe_request', $reviewed->triage_class->value);
        $this->assertDatabaseHas('communication_suppressions', ['reason' => 'unsubscribed']);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_followup_is_recommendation_only_with_zero_steps(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        config()->set('ai-sales.outreach.followup_planning_enabled', true);

        $plan = app(OutreachFollowUpRecommendationService::class)->recommend($dispatch, $actor);

        $this->assertSame('scheduled_disabled', $plan->status->value);
        $this->assertSame(0, $plan->max_follow_ups);
        $this->assertDatabaseCount('outreach_follow_up_steps', 0);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    private function event(int $sendingId, string $status, int $sequence): MailingEvent
    {
        return MailingEvent::query()->create([
            'provider' => 'unisender_go',
            'event_fingerprint' => hash('sha256', $sendingId.'-'.$status.'-'.$sequence),
            'sending_id' => $sendingId,
            'event_name' => 'email_status',
            'normalized_event_type' => 'email_status',
            'status' => $status,
            'normalized_status' => $status,
            'event_time' => now()->addSecond($sequence),
            'verified_at' => now(),
            'safe_summary' => 'test_normalized_event',
            'created_at' => now(),
        ]);
    }
}
