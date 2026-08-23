<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\ExecuteProspectingSearchQuery;
use App\Domain\AiSales\Services\ProspectingCandidateService;
use App\Domain\AiSales\Services\UnitBusinessContextService;
use App\Domain\AiSales\Services\UnitProductMatchService;
use App\Domain\AiSales\Workflows\PublicCompanyResearchWorkflow;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchJob;
use App\Models\Unit;
use App\Models\UnitContactContextLink;
use App\Models\Uri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Repository-owned isolated SQLite fixture processor; never registered as the runtime implementation. */
final class SyntheticClientAcquisitionCampaignStageProcessor implements ClientAcquisitionCampaignStageProcessorInterface
{
    public function __construct(
        private readonly DefaultClientAcquisitionCampaignStageProcessor $runtime,
        private readonly ApproveProspectingQueryPlan $plans,
        private readonly ExecuteProspectingSearchQuery $search,
        private readonly PublicCompanyResearchWorkflow $research,
        private readonly ProspectingCandidateService $candidates,
        private readonly UnitBusinessContextService $contexts,
        private readonly UnitProductMatchService $matches,
    ) {}

    public function process(ClientAcquisitionCampaign $campaign, AiAgentRun $run, AiAgentRunStep $step, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $this->assertSyntheticBoundary();

        return match ($step->step_type) {
            'plan_queries' => $this->plan($campaign, $run, $step, $actor),
            'execute_approved_yandex_searches' => $this->search($run, $actor),
            'safe_public_fetch_and_research' => $this->research($run, $actor),
            'ingest_candidates' => $this->ingest($run, $actor),
            'product_match_or_review' => $this->approveMatches($campaign, $run, $actor),
            default => $this->runtime->process($campaign, $run, $step, $actor),
        };
    }

    private function plan(ClientAcquisitionCampaign $campaign, AiAgentRun $run, AiAgentRunStep $step, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $outcome = $this->runtime->process($campaign, $run, $step, $actor);
        $queries = $this->plans->handle($this->job($run), $actor);

        return ClientAcquisitionCampaignStageOutcome::completed(['queries' => $queries->count(), 'human_reviewed' => true]);
    }

    private function search(AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $requests = 0;
        foreach ($job->queries()->where('plan_status', 'approved')->orderBy('sequence')->get() as $query) {
            $execution = $this->search->handle($query, $actor);
            $requests += (int) $execution->request_count;
        }
        $run->update(['accumulated_searches' => $requests]);

        return ClientAcquisitionCampaignStageOutcome::completed([
            'provider' => 'existing_yandex', 'transport' => 'fake', 'requests' => $requests,
            'results' => $job->searchResults()->count(), 'live_http' => 0,
        ]);
    }

    private function research(AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $completed = 0;
        foreach ($job->searchResults()->whereNull('duplicate_of_id')->orderBy('rank')->limit(3)->get() as $result) {
            $fetch = $result->publicFetch()->firstOrCreate([], [
                'status' => 'completed',
                'final_url' => $result->canonical_url,
                'final_url_hash' => hash('sha256', $result->canonical_url),
                'registrable_domain' => $result->registrable_domain,
                'content_type' => 'text/html',
                'byte_count' => 256,
                'duration_ms' => 0,
                'page_title' => $result->title,
                'meta_description' => 'Repository-owned synthetic public company page for broccoli buyers.',
                'headings' => ['Synthetic buyer profile'],
                'text_excerpt' => 'Fictional company uses frozen broccoli in food production in Saint Petersburg.',
                'same_domain_links' => [],
                'protected_channels' => [],
                'channel_count' => 0,
                'content_hash' => hash('sha256', 'stage14-page|'.$result->result_hash),
                'trust_level' => 'untrusted',
                'instruction_authority' => 'none',
                'robots_status' => 'repository_fixture',
                'fetched_at' => now(),
            ]);
            if ($fetch->status === 'completed') {
                $result->update(['fetch_status' => 'completed']);
                $record = $this->research->execute($result->fresh('publicFetch'), $actor);
                $completed += $record->status === 'completed' ? 1 : 0;
            }
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'synthetic_pages' => $completed, 'fake_research' => $completed, 'live_http' => 0,
        ]);
    }

    private function ingest(AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        if ($job->candidates()->exists()) {
            return ClientAcquisitionCampaignStageOutcome::completed(['candidates' => $job->candidates()->count(), 'idempotent' => true]);
        }
        $query = $job->queries()->where('plan_status', 'approved')->firstOrFail();
        $this->existingUnit($actor, 'Stage14 Existing Buyer', 'https://stage14-existing.example');
        $this->existingUnit($actor, 'Stage14 Probable Buyer', null);
        $fixtures = [
            $this->fixture('Stage14 Existing Buyer', 'https://stage14-existing.example', ['stage14-existing.example', 'registry-stage14.example']),
            $this->fixture('Stage14 Probable Buyer', null, ['probable-stage14.example', 'registry-stage14.example']),
            $this->fixture('Stage14 New Broccoli Buyer', 'https://stage14-new-buyer.example', ['stage14-new-buyer.example', 'registry-stage14.example']),
        ];
        foreach ($fixtures as $fixture) {
            $candidate = $this->candidates->createFixture($job, $fixture, $actor, true, $query);
            $candidate->update(['ai_agent_run_id' => $run->id]);
        }

        return ClientAcquisitionCampaignStageOutcome::completed(['candidates' => 3, 'source' => 'repository_fixture']);
    }

    private function approveMatches(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $created = $job->candidates()->where('status', 'new_unit_created')->first();
        if ($created?->resolved_unit_id) {
            $context = $created->resolvedUnit->businessContexts()->where('lane', 'sales')->firstOrFail();
            $context->update(['stage' => 'qualified']);
            UnitContactContextLink::query()->where('unit_business_context_id', $context->id)->update([
                'verification_status' => ObservationVerificationStatus::Verified->value,
                'last_verified_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($context->productMatches()->whereIn('status', ['suggested', 'reviewed'])->get() as $match) {
                $this->matches->review($match, UnitProductMatchStatus::Approved, $actor);
            }
        }

        return $this->runtime->process($campaign, $run, AiAgentRunStep::query()->findOrFail($run->steps()->where('step_type', 'product_match_or_review')->value('id')), $actor);
    }

    private function existingUnit(User $actor, string $name, ?string $url): Unit
    {
        $unit = Unit::query()->without(['fields', 'labels', 'telephones', 'uris'])->firstOrCreate([
            'name' => $name,
        ], ['is_customer' => false, 'is_supplier' => false]);
        $context = $unit->businessContexts()->where('lane', 'sales')->first()
            ?: $this->contexts->upsert($unit, [
                'lane' => 'sales', 'role_code' => 'prospective_customer', 'stage' => 'researching',
                'status' => 'active', 'source' => 'stage14-synthetic',
            ], $actor);
        if ($url) {
            $uri = Uri::query()->firstOrCreate(['address' => $url]);
            $unit->uris()->syncWithoutDetaching([$uri->id]);
            UnitContactContextLink::query()->firstOrCreate([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context->id,
                'channel_type' => 'uri',
                'normalized_hash' => hash('sha256', 'uri|'.$url),
            ], [
                'uri_id' => $uri->id,
                'channel_value_snapshot' => parse_url($url, PHP_URL_HOST),
                'contact_role' => 'business_general',
                'verification_status' => ObservationVerificationStatus::Verified,
                'data_classification' => DataClassification::Public,
                'visibility_scope' => UnitVisibilityScope::SalesLane,
                'communication_state' => 'review_required',
                'review_required' => true,
                'last_verified_at' => now(),
                'created_by' => $actor->id,
            ]);
        }

        return $unit;
    }

    private function fixture(string $name, ?string $website, array $sourceDomains): array
    {
        return [
            'working_name' => $name,
            'website' => $website,
            'location_display' => 'Санкт-Петербург',
            'public_activity_summary' => 'Fictional food producer using frozen broccoli in a repository-owned scenario.',
            'relevance_summary' => 'Direct synthetic evidence of broccoli product relevance.',
            'confidence_components' => ['relevance' => 95, 'identity' => 95],
            'sources' => collect($sourceDomains)->map(fn (string $domain, int $index): array => [
                'type' => $index === 0 ? 'corporate_website' : 'public_search',
                'reference' => 'repository-fixture:stage14:'.hash('sha256', $name.'|'.$domain),
                'url' => 'https://'.$domain.'/stage14-evidence',
                'title' => 'Synthetic public evidence',
                'excerpt' => 'Fictional independent source for deterministic Stage 14 acceptance.',
                'confidence' => 95,
                'source_quality' => 95,
            ])->all(),
        ];
    }

    private function job(AiAgentRun $run): ProspectingSearchJob
    {
        $jobId = ClientAcquisitionCampaignRunLink::query()->where('ai_agent_run_id', $run->id)->value('prospecting_search_job_id');

        return ProspectingSearchJob::query()->findOrFail($jobId);
    }

    private function assertSyntheticBoundary(): void
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlite'
            || DB::connection()->getDriverName() !== 'sqlite'
            || ! (bool) config('ai-sales.campaigns.synthetic_fixture_mode', false)) {
            throw new LogicException('Stage 14 synthetic processor requires explicit local/testing isolated SQLite fixture mode.');
        }
    }
}
