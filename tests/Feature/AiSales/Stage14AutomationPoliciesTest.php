<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\AutonomousOutreachDraftPolicy;
use App\Domain\AiSales\Campaigns\AutonomousOutreachDraftService;
use App\Domain\AiSales\Campaigns\AutonomousUnitCreationPolicy;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignHashes;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Enums\CandidateResolutionOutcome;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Models\CommunicationPermission;
use App\Models\Entity;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\UnitProspectPrioritySnapshot;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Stage14AutomationPoliciesTest extends Stage14TestCase
{
    public function test_auto_unit_default_off_blocks_before_any_unit_entity_or_consent_mutation(): void
    {
        $actor = $this->campaignUser(true);
        $product = $this->campaignProduct('Strict Unit Product');
        $campaign = $this->approvedCampaign($actor, $product, [
            'automation_mode' => 'autonomous_reviewed',
            'auto_unit_approved' => true,
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'auto-unit-policy');
        $job = $this->approvedJob($actor, 'buyer_discovery', null, $product);
        $campaign->runLinks()->where('ai_agent_run_id', $run->id)->update(['prospecting_search_job_id' => $job->id]);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Strict New Buyer',
            'website' => 'https://strict-new-buyer.example',
            'confidence_components' => ['relevance' => 95, 'identity' => 95],
            'sources' => [
                ['type' => 'corporate_website', 'url' => 'https://strict-new-buyer.example/about', 'reference' => 'fixture:stage14:primary'],
                ['type' => 'public_search', 'url' => 'https://independent-registry.example/strict-buyer', 'reference' => 'fixture:stage14:independent'],
            ],
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $candidate = $candidate->fresh(['sources', 'channels', 'products']);
        $this->assertSame(ProspectingCandidateStatus::NewUnitReview, $candidate->status);
        $this->assertSame(CandidateResolutionOutcome::NewUnitAllowed, $candidate->resolution_outcome);

        config()->set('ai-sales.campaigns.auto_create_unit_enabled', false);
        $this->expectException(NotFoundHttpException::class);
        try {
            app(AutonomousUnitCreationPolicy::class)->assertEligible($campaign->fresh(), $candidate);
        } finally {
            config()->set('ai-sales.campaigns.auto_create_unit_enabled', true);
        }
    }

    public function test_auto_unit_eligible_path_is_idempotent_and_duplicate_or_personal_only_state_blocks(): void
    {
        $actor = $this->campaignUser(true);
        $product = $this->campaignProduct('Autonomous Unit Product');
        $campaign = $this->approvedCampaign($actor, $product, [
            'automation_mode' => 'autonomous_reviewed',
            'auto_unit_approved' => true,
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'auto-unit-create');
        $job = $this->approvedJob($actor, 'buyer_discovery', null, $product);
        $campaign->runLinks()->where('ai_agent_run_id', $run->id)->update(['prospecting_search_job_id' => $job->id]);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Autonomous New Buyer',
            'website' => 'https://autonomous-new-buyer.example',
            'confidence_components' => ['relevance' => 95, 'identity' => 95],
            'sources' => [
                ['type' => 'corporate_website', 'url' => 'https://autonomous-new-buyer.example/about', 'reference' => 'fixture:stage14:auto-primary'],
                ['type' => 'public_search', 'url' => 'https://public-registry.example/autonomous-buyer', 'reference' => 'fixture:stage14:auto-independent'],
            ],
        ]);
        app(ResolveProspectingCandidate::class)->evaluate($candidate, $actor);
        $candidate = $candidate->fresh();
        $entities = Entity::query()->without(['buildings', 'classification', 'country'])->count();
        $permissions = CommunicationPermission::query()->count();

        $resolver = app(ResolveProspectingCandidate::class);
        $unit = $resolver->createNewUnitAutonomously($candidate, $campaign->fresh(), $actor);
        $replay = $resolver->createNewUnitAutonomously($candidate->fresh(), $campaign->fresh(), $actor);

        $this->assertSame($unit->id, $replay->id);
        $this->assertSame('sales', $unit->businessContexts()->sole()->lane->value);
        $this->assertSame('prospective_customer', $unit->businessContexts()->sole()->role_code->value);
        $this->assertSame('autonomous_unit_creation_v1', $candidate->fresh()->resolution_reason_code);
        $this->assertSame($entities, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertSame($permissions, CommunicationPermission::query()->count());

        $blocked = $this->candidate($job, $actor, [
            'working_name' => 'Probable Duplicate Review',
            'website' => 'https://probable-review.example',
            'sources' => [
                ['type' => 'corporate_website', 'url' => 'https://probable-review.example', 'reference' => 'fixture:stage14:probable'],
                ['type' => 'public_search', 'url' => 'https://registry-two.example/probable', 'reference' => 'fixture:stage14:probable-two'],
            ],
        ]);
        $blocked->update([
            'status' => ProspectingCandidateStatus::ProbableExistingReview,
            'resolution_outcome' => CandidateResolutionOutcome::ProbableExistingReview,
        ]);
        try {
            app(AutonomousUnitCreationPolicy::class)->assertEligible($campaign->fresh(), $blocked->fresh());
            $this->fail('Probable duplicate must not auto-create Unit.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_unit_candidate_state_blocked', $exception->errorCode);
        }

        $insufficient = $this->candidate($job, $actor, [
            'working_name' => 'Insufficient Source Review',
            'website' => 'https://one-source-only.example',
            'sources' => [[
                'type' => 'corporate_website',
                'url' => 'https://one-source-only.example/about',
                'reference' => 'fixture:stage14:one-source',
            ]],
        ]);
        $insufficient->update([
            'status' => ProspectingCandidateStatus::NewUnitReview,
            'resolution_outcome' => CandidateResolutionOutcome::NewUnitAllowed,
        ]);
        try {
            app(AutonomousUnitCreationPolicy::class)->assertEligible($campaign->fresh(), $insufficient->fresh());
            $this->fail('Independent source threshold must be enforced.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_unit_independent_sources_required', $exception->errorCode);
        }

        $personal = $this->candidate($job, $actor, [
            'working_name' => 'Personal Only Review',
            'website' => 'https://personal-only-corp.example',
            'sources' => [
                ['type' => 'corporate_website', 'url' => 'https://personal-only-corp.example', 'reference' => 'fixture:stage14:personal'],
                ['type' => 'public_search', 'url' => 'https://registry-three.example/personal', 'reference' => 'fixture:stage14:personal-two'],
            ],
        ]);
        $personal->channels()->update(['contact_role' => 'person_specific']);
        $personal->update([
            'status' => ProspectingCandidateStatus::NewUnitReview,
            'resolution_outcome' => CandidateResolutionOutcome::NewUnitAllowed,
        ]);
        try {
            app(AutonomousUnitCreationPolicy::class)->assertEligible($campaign->fresh(), $personal->fresh());
            $this->fail('Personal-only contacts must not authorize Unit creation.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_unit_personal_only_contact_blocked', $exception->errorCode);
        }

        $personal->channels()->update(['contact_role' => 'business_general']);
        $campaign->update(['max_units_per_day' => 1]);
        $campaign->update([
            'approval_snapshot_hash' => app(ClientAcquisitionCampaignHashes::class)->approval($campaign->fresh()),
        ]);
        try {
            app(AutonomousUnitCreationPolicy::class)->assertEligible($campaign->fresh(), $personal->fresh());
            $this->fail('Exhausted campaign daily Unit cap must block.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_unit_cap_exhausted', $exception->errorCode);
        }
    }

    public function test_auto_draft_requires_current_scores_and_local_corporate_channel_then_stops_at_review(): void
    {
        $actor = $this->campaignUser(true, ['ai_sales.scoring.review']);
        [$actor, $unit, $context, $product, $match] = $this->outreachFixture($actor);
        $campaign = $this->approvedCampaign($actor, $product, [
            'automation_mode' => 'autonomous_reviewed',
            'auto_draft_approved' => true,
        ]);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'auto-draft-binding');
        $job = $this->approvedJob($actor, 'buyer_discovery', null, $product);
        $campaign->runLinks()->where('ai_agent_run_id', $run->id)->update(['prospecting_search_job_id' => $job->id]);
        $candidate = $this->candidate($job, $actor, [
            'working_name' => 'Bound Draft Candidate',
            'website' => 'https://bound-draft-candidate.example',
            'sources' => [[
                'type' => 'corporate_website',
                'url' => 'https://bound-draft-candidate.example/about',
                'reference' => 'fixture:stage14:draft-binding',
            ]],
        ]);
        $candidate->update([
            'status' => ProspectingCandidateStatus::NewUnitCreated,
            'resolution_outcome' => CandidateResolutionOutcome::NewUnitAllowed,
            'resolved_unit_id' => $unit->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'resolution_reason_code' => 'repository_fixture_binding',
        ]);
        $match->update(['prospecting_candidate_product_id' => $candidate->products()->value('id')]);
        $this->scoreSnapshots($actor->id, $unit->id, $context->id, $match->id, 50, 50, 'low');

        config()->set('ai-sales.campaigns.auto_draft_enabled', false);
        try {
            app(AutonomousOutreachDraftPolicy::class)->assertEligible($campaign, $context, $match);
            $this->fail('Auto draft must be default-off.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        } finally {
            config()->set('ai-sales.campaigns.auto_draft_enabled', true);
        }

        try {
            app(AutonomousOutreachDraftPolicy::class)->assertEligible($campaign->fresh(), $context, $match->fresh());
            $this->fail('Low score/confidence must block automatic drafting.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_draft_score_threshold_blocked', $exception->errorCode);
        }
        $this->scoreSnapshots($actor->id, $unit->id, $context->id, $match->id, 90, 90, 'high');

        $draft = app(AutonomousOutreachDraftService::class)->create($campaign->fresh(), $context, $match, $actor);

        $this->assertSame('review_required', $draft->status->value);
        $this->assertNull($draft->email_id);
        $this->assertNull($draft->unit_contact_context_link_id);
        $this->assertFalse($draft->reviews()->exists());
        $this->assertFalse($draft->dispatches()->exists());
        $this->assertGreaterThan(0, $draft->currentRevision()->claims()->count());
        $this->assertDatabaseCount('outreach_dispatches', 0);
        $this->assertDatabaseCount('sendings', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();

        $context->unit->contactContextLinks()->update(['archived_at' => now()]);
        try {
            app(AutonomousOutreachDraftPolicy::class)->assertEligible($campaign->fresh(), $context->fresh(), $match->fresh());
            $this->fail('Missing local corporate channel must block automatic drafting.');
        } catch (PolicyViolation $exception) {
            $this->assertSame('auto_draft_corporate_channel_required', $exception->errorCode);
        }
    }

    private function scoreSnapshots(
        int $actorId,
        int $unitId,
        int $contextId,
        int $matchId,
        int $score,
        int $confidence,
        string $tag,
    ): void {
        $common = [
            'computed_score' => $score, 'effective_score' => $score, 'confidence' => $confidence,
            'band' => 'high', 'eligibility' => 'review_required', 'review_status' => 'unreviewed',
            'next_best_action' => 'human_review', 'definition_version' => '1',
            'definition_hash' => hash('sha256', 'stage14-definition'),
            'input_hash' => hash('sha256', 'stage14-input|'.$tag),
            'evidence_hash' => hash('sha256', 'stage14-evidence|'.$tag),
            'origin' => 'deterministic', 'computed_by' => $actorId,
        ];
        UnitProductRelevanceSnapshot::query()->create([
            'unit_product_match_id' => $matchId, 'unit_id' => $unitId,
            'unit_business_context_id' => $contextId, 'definition_code' => 'product_relevance.v1',
            'idempotency_key' => hash('sha256', 'stage14-product-score|'.$matchId.'|'.$tag), ...$common,
        ]);
        UnitProspectPrioritySnapshot::query()->create([
            'unit_id' => $unitId, 'unit_business_context_id' => $contextId,
            'definition_code' => 'prospect_priority.v1',
            'idempotency_key' => hash('sha256', 'stage14-priority-score|'.$contextId.'|'.$tag), ...$common,
        ]);
    }
}
