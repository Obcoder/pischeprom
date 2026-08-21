<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignSearchJobService;
use App\Domain\AiSales\Campaigns\ResumeClientAcquisitionCampaignRun;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\ProspectingSearchExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class Stage14CampaignQueryPlanResumeTest extends Stage14TestCase
{
    public function test_campaign_review_aggregates_plan_and_human_approval_resumes_exact_linked_run(): void
    {
        [$actor, $campaign, $run, $job, $queries] = $this->waitingPlanFixture();

        $reviews = collect(app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor));
        $this->assertCount(1, $reviews);
        $review = $reviews->first();
        $this->assertSame('query_plan_review', $review['category']);
        $this->assertSame('prospecting_search_job', $review['source_type']);
        $this->assertSame($job->id, $review['search_job_id']);
        $this->assertSame($job->public_id, $review['search_job_public_id']);
        $this->assertSame($queries->count(), $review['safe_evidence']['query_count']);
        $this->assertCount($queries->count(), $review['safe_evidence']['queries']);
        $this->assertArrayNotHasKey('raw_body', $review['safe_evidence']);

        $response = $this->actingAs($actor)->postJson(
            '/api/ai-sales/prospecting/jobs/'.$job->public_id.'/search-plan/approve',
            [],
        );
        $response->assertOk()->assertJsonPath('meta.campaign_resume_queued', true);
        $this->assertSame(0, $job->queries()->where('plan_status', 'review_required')->count());
        $this->assertSame($queries->count(), $job->queries()->where('plan_status', 'approved')->count());
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, 1);
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, fn ($queued): bool => $queued->runId === $run->id && $queued->actorUserId === $run->initiator_user_id
        );
        Http::assertNothingSent();
        Mail::assertNothingSent();

        $ui = file_get_contents(resource_path('js/Components/AiSales/ClientAcquisitionCampaignDashboard.vue'));
        $this->assertStringContainsString('План поисковых запросов', $ui);
        $this->assertStringContainsString('Одобрить план и продолжить', $ui);
        $this->assertStringContainsString('/search-plan/approve', $ui);
        $this->assertStringNotContainsString('search-execute', $ui);
    }

    public function test_settled_search_batch_resumes_same_run_once_without_domain_mutations(): void
    {
        [$actor, $campaign, $run, $job, $queries] = $this->waitingPlanFixture();
        app(ApproveProspectingQueryPlan::class)->handle($job, $actor);
        $run->update(['safe_error_code' => 'search_jobs_dispatched']);
        $unitsBefore = \App\Models\Unit::query()->count();
        $entitiesBefore = \App\Models\Entity::query()->count();
        Queue::fake();

        foreach ($queries as $index => $query) {
            ProspectingSearchExecution::query()->create([
                'prospecting_search_job_id' => $job->id,
                'prospecting_search_query_id' => $query->id,
                'initiated_by' => $actor->id,
                'profile_code' => 'prospecting_b2b_discovery',
                'provider_code' => 'existing_yandex',
                'request_hash' => hash('sha256', 'settled-search-'.$query->id),
                'plan_hash' => $query->plan_hash,
                'status' => 'completed',
                'attempt' => 1,
                'request_count' => 1,
                'result_count' => 0,
                'completed_at' => now(),
            ]);
            $resumed = app(ResumeClientAcquisitionCampaignRun::class)->afterSearchBatchSettled($job->fresh());
            $this->assertSame($index === $queries->count() - 1, $resumed);
        }

        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, 1);
        Queue::assertPushed(ExecuteClientAcquisitionCampaignRunJob::class, fn ($queued): bool => $queued->runId === $run->id && $queued->actorUserId === $run->initiator_user_id
        );
        $this->assertSame('running', $campaign->fresh()->status->value);
        $this->assertSame('requires_action', $run->fresh()->status->value);
        $this->assertSame($unitsBefore, \App\Models\Unit::query()->count());
        $this->assertSame($entitiesBefore, \App\Models\Entity::query()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function waitingPlanFixture(): array
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'query-plan-resume');
        $job = app(ClientAcquisitionCampaignSearchJobService::class)->ensure($campaign, $run);
        $queries = app(PlanProspectingQueries::class)->handle($job, $actor);
        $run->steps()->where('sequence', '<', 4)->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $run->steps()->where('sequence', 4)->update([
            'status' => 'requires_action',
            'safe_error_code' => 'query_plan_review_required',
            'safe_error_summary' => 'Human review of the current code-owned query plan is required.',
        ]);
        $run->update([
            'status' => 'requires_action',
            'current_step' => 4,
            'safe_error_code' => 'query_plan_review_required',
            'safe_error_summary' => 'Human review of the current code-owned query plan is required.',
        ]);

        Queue::fake();

        return [$actor, $campaign->fresh(), $run->fresh(), $job->fresh(), $queries];
    }
}
