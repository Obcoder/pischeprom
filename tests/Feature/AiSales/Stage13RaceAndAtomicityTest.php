<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\CommunicationSuppressionService;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Domain\AiSales\Outreach\OutreachFollowUpRecommendationService;
use App\Jobs\AiSales\SendOutreachDispatchJob;
use App\Models\OutreachDispatchDecision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class Stage13RaceAndAtomicityTest extends Stage13TestCase
{
    public function test_suppression_added_after_queue_blocks_worker(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, $context, , , , $contact] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();
        app(CommunicationSuppressionService::class)->create($actor, $unit, $context, [
            'scope' => 'endpoint', 'unit_contact_context_link_id' => $contact->id,
            'reason' => 'do_not_contact', 'source' => 'stage13_race_test',
            'evidence_reference' => 'repository-fixture:stage13-race-dnc',
            'evidence_hash' => hash('sha256', 'stage13-race-dnc'),
        ]);

        app(OutreachDispatchService::class)->deliver($dispatch->id);

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        Http::assertNothingSent();
    }

    public function test_queue_replay_after_permission_revocation_blocks_without_second_job(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        $url = "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue";
        $this->actingAs($actor)->postJson($url)->assertAccepted();
        app(\App\Domain\AiSales\Outreach\CommunicationPermissionService::class)->revoke(
            $dispatch->permission,
            $actor,
            'revoked_before_queue_replay',
            null,
        );

        $this->postJson($url)->assertConflict();

        $this->assertSame('blocked', $dispatch->fresh()->state->value);
        Queue::assertPushed(SendOutreachDispatchJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_suppression_preserves_provider_acceptance_and_cancels_followup(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, $context, , , , $contact] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $dispatch->forceFill(['state' => 'provider_accepted', 'provider_accepted_at' => now()])->save();
        config()->set('ai-sales.outreach.followup_planning_enabled', true);
        $plan = app(OutreachFollowUpRecommendationService::class)->recommend($dispatch, $actor);

        app(CommunicationSuppressionService::class)->create($actor, $unit, $context, [
            'scope' => 'endpoint', 'unit_contact_context_link_id' => $contact->id,
            'reason' => 'do_not_contact', 'source' => 'stage13_race_test',
            'evidence_reference' => 'repository-fixture:stage13-provider-bound-dnc',
            'evidence_hash' => hash('sha256', 'stage13-provider-bound-dnc'),
        ]);

        $this->assertSame('provider_accepted', $dispatch->fresh()->state->value);
        $this->assertSame('cancelled_suppression', $plan->fresh()->status->value);
        Http::assertNothingSent();
    }

    public function test_contact_invalidation_and_context_lane_change_block_worker(): void
    {
        foreach (['contact', 'lane'] as $scenario) {
            $fixture = $this->approvedOutreachFixture();
            [$actor, $unit, $context, , , $email] = $fixture;
            $dispatch = $this->prepareDispatch($fixture);
            $this->enableProviderQueue();
            $this->actingAs($actor)->postJson(
                "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
            )->assertAccepted();
            if ($scenario === 'contact') {
                $email->forceFill(['is_active' => false])->save();
            } else {
                $context->forceFill(['lane' => 'procurement', 'role_code' => 'prospective_supplier'])->save();
            }

            app(OutreachDispatchService::class)->deliver($dispatch->id);
            $this->assertSame('blocked', $dispatch->fresh()->state->value, $scenario);
        }
        Http::assertNothingSent();
    }

    public function test_ambiguous_acceptance_is_operator_review_and_never_resent(): void
    {
        $this->allowExpectedHttpRequests = true;
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        Http::fake(['*' => Http::failedConnection()]);
        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
        )->assertAccepted();

        $service = app(OutreachDispatchService::class);
        $service->deliver($dispatch->id);
        $service->deliver($dispatch->id);

        $dispatch->refresh();
        $this->assertSame('ambiguous_acceptance', $dispatch->state->value);
        $this->assertSame('ambiguous_acceptance', $dispatch->sending->safe_error_code);
        $this->assertSame('operator_review_required_no_resend', $dispatch->sending->safe_summary);
        Http::assertSentCount(1);
    }

    public function test_rolled_back_queue_transaction_pushes_no_job(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor] = $fixture;
        $dispatch = $this->prepareDispatch($fixture);
        $this->enableProviderQueue();
        OutreachDispatchDecision::creating(static function (): never {
            throw new \RuntimeException('synthetic rollback');
        });

        try {
            app(OutreachDispatchService::class)->queue($dispatch, $actor);
            $this->fail('Expected synthetic transaction rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('synthetic rollback', $exception->getMessage());
        }

        $this->assertSame('ready', $dispatch->fresh()->state->value);
        Queue::assertNotPushed(SendOutreachDispatchJob::class);
        Http::assertNothingSent();
    }
}
