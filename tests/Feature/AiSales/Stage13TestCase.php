<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Outreach\CommunicationPermissionService;
use App\Domain\AiSales\Outreach\Enums\CommunicationPermissionStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewDecision;
use App\Domain\AiSales\Outreach\Enums\OutreachReviewType;
use App\Domain\AiSales\Outreach\OutreachDraftService;
use App\Models\OutreachDraft;
use Illuminate\Support\Str;

abstract class Stage13TestCase extends Stage12TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.outreach.dispatch_pipeline_enabled' => true,
            'ai-sales.outreach.queue_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.event_ingestion_enabled' => false,
            'ai-sales.outreach.reply_correlation_enabled' => false,
            'ai-sales.outreach.reply_triage_enabled' => false,
            'ai-sales.outreach.followup_planning_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.limits.global_daily_sends' => 0,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 0,
            'ai-sales.outreach.limits.max_follow_ups' => 0,
            'ai-sales.outreach.limits.provider_retries' => 0,
            'ai-sales.outreach.limits.provider_failover' => false,
        ]);
    }

    protected function outreachUser(array $extra = []): \App\Models\User
    {
        return parent::outreachUser(array_values(array_unique([
            'ai_sales.outreach.dispatch.view', 'ai_sales.outreach.dispatch.prepare',
            'ai_sales.outreach.dispatch.queue', 'ai_sales.outreach.dispatch.cancel',
            'ai_sales.outreach.events.view', 'ai_sales.outreach.replies.view',
            'ai_sales.outreach.replies.review', 'ai_sales.outreach.followups.manage',
            ...$extra,
        ])));
    }

    /** @return array{\App\Models\User, \App\Models\Unit, \App\Models\UnitBusinessContext, \App\Models\Product, \App\Models\UnitProductMatch, \App\Models\Email, \App\Models\UnitContactContextLink, OutreachDraft} */
    protected function approvedOutreachFixture(): array
    {
        [$actor, $unit, $context, $product, $match, $email, $contact] = $this->outreachFixture();
        $drafts = app(OutreachDraftService::class);
        $permissions = app(CommunicationPermissionService::class);
        $draft = $drafts->create($actor, $unit, $context, $this->draftPayload($context, $match, $contact));
        $revision = $drafts->generate($draft, $actor);
        $permission = $permissions->create($actor, $unit, $context, $contact->fresh('email'), [
            'purpose' => 'advertising_outreach',
            'product_id' => $product->id,
            'valid_from' => now()->subMinute(),
            'valid_until' => now()->addDays(7),
            'evidence' => [[
                'type' => 'written_response',
                'reference' => 'repository-fixture:stage13-permission-v1',
                'content_hash' => hash('sha256', 'stage13-permission-evidence'),
                'captured_at' => now(),
                'source_controller' => 'stage13_test',
            ]],
        ]);
        $permissions->review($permission, $actor, CommunicationPermissionStatus::Granted, 'human_evidence_review', null);
        foreach (OutreachReviewType::cases() as $type) {
            $drafts->review(
                $draft->fresh(), $revision, $actor, $type,
                OutreachReviewDecision::Approved, 'human_review', null,
            );
        }

        return [$actor, $unit, $context, $product, $match, $email, $contact, $draft->fresh()];
    }

    protected function prepareDispatch(array $fixture): \App\Models\OutreachDispatch
    {
        [$actor, $unit, , , , , , $draft] = $fixture;

        $id = $this->actingAs($actor)->postJson(
            "/api/ai-sales/units/{$unit->id}/outreach/drafts/{$draft->id}/dispatches",
            ['idempotency_key' => (string) Str::uuid()],
        )->assertCreated()->json('data.id');

        return \App\Models\OutreachDispatch::query()->findOrFail($id);
    }

    protected function enableProviderQueue(): void
    {
        config()->set([
            'ai-sales.outreach_sending_enabled' => true,
            'ai-sales.outreach.dispatch_enabled' => true,
            'ai-sales.outreach.queue_enabled' => true,
            'ai-sales.outreach.provider_send_enabled' => true,
            'ai-sales.outreach.limits.global_daily_sends' => 10,
            'ai-sales.outreach.limits.per_domain_daily_sends' => 10,
            'services.unisender_go.enabled' => true,
            'services.unisender_go.api_key' => 'test-only-placeholder-not-a-real-key',
        ]);
    }
}
