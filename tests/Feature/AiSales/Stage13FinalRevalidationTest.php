<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachDispatchService;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Models\CommunicationPermission;
use App\Models\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Stage13FinalRevalidationTest extends Stage13TestCase
{
    public function test_prepare_blocks_a_corrupted_permission_endpoint_scope(): void
    {
        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $otherEmail = Email::query()->create([
            'address' => 'other-permission-endpoint@example.test',
            'source' => 'stage13_test',
            'is_active' => true,
        ]);
        CommunicationPermission::query()->where('unit_contact_context_link_id', $draft->unit_contact_context_link_id)
            ->update(['email_id' => $otherEmail->id]);

        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches",
            ['idempotency_key' => (string) Str::uuid()],
        )->assertUnprocessable()->assertJsonPath('code', 'outreach_prepare_blocked');

        $this->assertDatabaseCount('outreach_dispatches', 0);
        Http::assertNothingSent();
    }

    public function test_worker_blocks_stale_claim_product_and_renderer_guards(): void
    {
        $scenarios = [
            'claim' => function ($dispatch): void {
                DB::table('outreach_draft_claims')
                    ->where('outreach_draft_revision_id', $dispatch->outreach_draft_revision_id)
                    ->update(['fresh_until' => now()->subMinute()]);
            },
            'product' => function ($dispatch): void {
                DB::table('unit_product_matches')->where('id', $dispatch->unit_product_match_id)
                    ->update(['stale_after' => now()->subMinute()]);
            },
            'product_scope' => function ($dispatch): void {
                $otherUnit = $this->unit(['name' => 'Stage 13 mismatched Product scope']);
                DB::table('unit_product_matches')->where('id', $dispatch->unit_product_match_id)
                    ->update(['unit_id' => $otherUnit->id]);
            },
            'renderer' => function ($dispatch): void {
                DB::table('outreach_draft_revisions')->where('id', $dispatch->outreach_draft_revision_id)
                    ->update(['renderer_hash' => hash('sha256', 'tampered-renderer')]);
            },
        ];

        foreach ($scenarios as $name => $mutate) {
            $fixture = $this->approvedOutreachFixture();
            [$actor, $unit] = $fixture;
            $dispatch = $this->prepareDispatch($fixture);
            $this->enableProviderQueue();
            $this->actingAs($actor)->postJson(
                "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
            )->assertAccepted();
            $mutate($dispatch);

            app(OutreachDispatchService::class)->deliver($dispatch->id);

            $this->assertSame('blocked', $dispatch->fresh()->state->value, $name);
        }

        Http::assertNothingSent();
    }

    public function test_queue_enforces_zero_daily_limit_and_recipient_cooldown(): void
    {
        $zeroLimitFixture = $this->approvedOutreachFixture();
        [$zeroActor, $zeroUnit] = $zeroLimitFixture;
        $zeroDispatch = $this->prepareDispatch($zeroLimitFixture);
        $this->enableProviderQueue();
        config()->set('ai-sales.outreach.limits.global_daily_sends', 0);

        $this->actingAs($zeroActor)->postJson(
            "/api/ai-sales/units/{$zeroUnit->id}/outreach/dispatches/{$zeroDispatch->id}/queue",
        )->assertConflict();
        $this->assertSame('blocked', $zeroDispatch->fresh()->state->value);

        $fixture = $this->approvedOutreachFixture();
        [$actor, $unit, , , , , , $draft] = $fixture;
        $prior = $this->prepareDispatch($fixture);
        $prior->forceFill(['state' => 'provider_accepted', 'provider_accepted_at' => now()])->save();
        $drafts = app(OutreachDraftService::class);
        $revision = $drafts->revise($draft->fresh(), $actor, $draft->currentRevision()->structured_content);
        foreach (OutreachReviewType::cases() as $type) {
            $drafts->review($draft->fresh(), $revision, $actor, $type, OutreachReviewDecision::Approved, 'human_review', null);
        }
        config()->set([
            'ai-sales.outreach.limits.global_daily_sends' => 10,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 10,
        ]);
        $next = $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches",
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated()->json('data.id');

        $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$next}/queue",
        )->assertConflict();
        $this->assertDatabaseHas('outreach_dispatches', ['id' => $next, 'state' => 'blocked']);
        Http::assertNothingSent();
    }

    public function test_worker_blocks_sender_and_dispatch_scope_tampering(): void
    {
        foreach (['sender', 'scope'] as $scenario) {
            $fixture = $this->approvedOutreachFixture();
            [$actor, $unit] = $fixture;
            $dispatch = $this->prepareDispatch($fixture);
            $this->enableProviderQueue();
            $this->actingAs($actor)->postJson(
                "/api/ai-sales/units/{$unit->id}/outreach/dispatches/{$dispatch->id}/queue",
            )->assertAccepted();
            if ($scenario === 'sender') {
                config()->set('services.unisender_go.reply_to', 'changed@example.test');
            } else {
                DB::table('outreach_dispatches')->where('id', $dispatch->id)
                    ->update(['purpose' => 'transactional']);
            }

            app(OutreachDispatchService::class)->deliver($dispatch->id);

            $this->assertSame('blocked', $dispatch->fresh()->state->value, $scenario);
        }

        Http::assertNothingSent();
    }
}
