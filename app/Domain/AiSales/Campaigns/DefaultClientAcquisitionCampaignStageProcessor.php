<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Enums\ProspectingCandidateStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Prospecting\DomainInvestigationPlanner;
use App\Domain\AiSales\Prospecting\PublicCompanyIdentityResolver;
use App\Domain\AiSales\Prospecting\ResultBusinessRoleClassifier;
use App\Domain\AiSales\Scoring\ProspectingScoreRecalculationService;
use App\Domain\AiSales\Services\ExecuteProspectingSearchJob;
use App\Domain\AiSales\Services\IngestProspectingSearchCandidate;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use App\Domain\AiSales\Services\ResolveProspectingCandidate;
use App\Domain\AiSales\Web\SafePublicPageFetcher;
use App\Domain\AiSales\Workflows\PublicCompanyResearchWorkflow;
use App\Models\AiAgentRun;
use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DefaultClientAcquisitionCampaignStageProcessor implements ClientAcquisitionCampaignStageProcessorInterface
{
    public function __construct(
        private readonly ClientAcquisitionCampaignHashes $hashes,
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignSearchJobService $searchJobs,
        private readonly PlanProspectingQueries $queryPlanner,
        private readonly ExecuteProspectingSearchJob $searchExecution,
        private readonly SafePublicPageFetcher $fetcher,
        private readonly PublicCompanyResearchWorkflow $research,
        private readonly ProspectingFeatureGuard $prospectingFeatures,
        private readonly IngestProspectingSearchCandidate $ingest,
        private readonly ResolveProspectingCandidate $resolution,
        private readonly ProspectingScoreRecalculationService $scoring,
        private readonly AutonomousOutreachDraftService $drafts,
        private readonly ResultBusinessRoleClassifier $businessRoles,
        private readonly DomainInvestigationPlanner $domainInvestigations,
        private readonly PublicCompanyIdentityResolver $companyIdentities,
    ) {}

    public function process(
        ClientAcquisitionCampaign $campaign,
        AiAgentRun $run,
        AiAgentRunStep $step,
        User $actor,
    ): ClientAcquisitionCampaignStageOutcome {
        return match ($step->step_type) {
            'validate_campaign' => $this->validate($campaign),
            'create_or_reuse_product_search_job' => $this->createJob($campaign, $run),
            'plan_queries' => $this->plan($run, $actor),
            'execute_approved_yandex_searches' => $this->search($campaign, $run, $actor),
            'normalize_and_dedupe_results' => $this->dedupe($run),
            'safe_public_fetch_and_research' => $this->research($campaign, $run, $actor),
            'ingest_candidates' => $this->ingest($campaign, $run, $actor),
            'deterministic_unit_resolution' => $this->resolve($run, $actor),
            'unit_creation_or_review' => $this->units($campaign, $run, $actor),
            'product_match_or_review' => $this->matches($run),
            'deterministic_scoring' => $this->score($campaign, $run, $actor),
            'outreach_draft_or_review' => $this->draft($campaign, $run, $actor),
            'update_progress_digest' => $this->digest($run),
            'stop' => ClientAcquisitionCampaignStageOutcome::completed(['stop' => true]),
            default => ClientAcquisitionCampaignStageOutcome::blocked('campaign_stage_unknown', 'Unknown server-owned campaign stage.'),
        };
    }

    private function validate(ClientAcquisitionCampaign $campaign): ClientAcquisitionCampaignStageOutcome
    {
        if (! $this->hashes->isCurrent($campaign)) {
            return ClientAcquisitionCampaignStageOutcome::blocked('campaign_approval_stale', 'Campaign approval hash is stale.');
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'purpose' => 'buyer_discovery', 'lane' => 'sales', 'role_code' => 'prospective_customer',
        ]);
    }

    private function createJob(ClientAcquisitionCampaign $campaign, AiAgentRun $run): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->searchJobs->ensure($campaign, $run);

        return ClientAcquisitionCampaignStageOutcome::completed(['search_job_id' => $job->id]);
    }

    private function plan(AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $queries = $job->queries()->whereNotNull('plan_hash')->where('plan_status', '!=', 'stale')->get();
        if ($queries->isEmpty()) {
            $queries = $this->queryPlanner->handle($job, $actor);
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'queries' => $queries->count(), 'review_required' => $queries->where('plan_status', 'review_required')->count(),
        ]);
    }

    private function search(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $queries = $job->queries()->whereNotNull('plan_hash')->where('plan_status', '!=', 'stale')->get();
        if ($queries->isEmpty() || $queries->contains(fn ($query) => $query->plan_status !== 'approved')) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'query_plan_review_required', 'Human review of the current code-owned query plan is required.',
                ['queries' => $queries->count()],
            );
        }
        $failed = $job->searchExecutions()->where('status', 'failed')->count();
        if ($failed > 0) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'search_provider_error_review', 'A safe search-provider failure requires operator review.', ['failed' => $failed],
            );
        }
        if ($job->searchExecutions()->where('status', 'completed')->count() === $queries->count()) {
            $requests = (int) $job->searchExecutions()->sum('request_count');
            $run->update(['accumulated_searches' => $requests]);

            return ClientAcquisitionCampaignStageOutcome::completed(['queries' => $queries->count(), 'requests' => $requests]);
        }
        $this->features->liveSearch();
        if ($campaign->max_search_requests_per_run < 1) {
            return ClientAcquisitionCampaignStageOutcome::blocked('campaign_search_budget_missing', 'Campaign search budget is zero.');
        }
        $ids = $this->searchExecution->handle($job, $actor);

        return ClientAcquisitionCampaignStageOutcome::pending(
            'search_jobs_dispatched', 'Approved bounded search jobs were dispatched.', ['queries' => count($ids)],
        );
    }

    private function dedupe(AiAgentRun $run): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $total = $job->searchResults()->count();
        if ($total < 1) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction('search_results_missing', 'Search produced no reviewable results.');
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'results' => $total,
            'unique_results' => $job->searchResults()->whereNull('duplicate_of_id')->count(),
            'domains' => $job->searchResults()->whereNull('duplicate_of_id')->distinct('domain_hash')->count('domain_hash'),
        ]);
    }

    private function research(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $this->features->liveResearch();
        $job = $this->job($run);
        $existingCandidates = $job->candidates()->count();
        if ($existingCandidates > 0) {
            return ClientAcquisitionCampaignStageOutcome::completed([
                'completed' => $job->searchResults()->where('research_status', 'completed')->count(),
                'candidates' => $existingCandidates,
                'manual_candidate_evidence_accepted' => true,
            ]);
        }
        $limit = min((int) $campaign->max_research_pages_per_run, (int) ($job->criteria_snapshot['max_page_fetch_attempts'] ?? 0));
        if ($limit < 1) {
            return ClientAcquisitionCampaignStageOutcome::blocked('campaign_research_budget_missing', 'Campaign research-page budget is zero.');
        }
        $domainLimit = min(
            max(1, (int) ($job->criteria_snapshot['max_domains'] ?? $limit)),
            max(1, (int) config('ai-sales.campaigns.limits.max_domains_per_run', 0)),
        );
        $available = $job->searchResults()->whereNull('duplicate_of_id')
            ->with(['publicFetch', 'research', 'job:id,lane'])
            ->orderBy('rank')->limit(max($limit * 5, 100))->get();
        $eligible = $available->filter(
            fn ($item): bool => $this->businessRoles->classify($item)->researchEligible,
        );
        $results = $this->domainInvestigations->select($eligible, $domainLimit, $limit);
        $completed = 0;
        $blocked = 0;
        foreach ($results as $result) {
            try {
                $fetch = $result->publicFetch ?: $this->fetcher->fetch($result, $actor);
                if ($fetch->status === 'completed') {
                    $record = $result->research ?: $this->research->execute($result->fresh('publicFetch'), $actor);
                    $completed += $record->status === 'completed' ? 1 : 0;
                }
            } catch (Throwable) {
                $blocked++;
            }
        }
        if ($completed < 1) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'public_research_review_required', 'No public research record completed; failures remain fail-closed.',
                ['attempted' => $results->count(), 'blocked' => $blocked],
            );
        }

        $reviewableDomains = 0;
        $domainsRequiringReview = 0;
        $investigated = $job->searchResults()->whereIn('domain_hash', $results->pluck('domain_hash')->unique())
            ->whereNull('duplicate_of_id')->with(['publicFetch', 'research', 'searchQuery', 'job:id,lane'])
            ->orderBy('rank')->get()->groupBy('domain_hash');
        foreach ($investigated as $domainResults) {
            $combined = $domainResults->map(fn ($item): string => implode(' ', array_filter([
                $item->title, $item->snippet, $item->publicFetch?->page_title,
                $item->publicFetch?->meta_description, $item->publicFetch?->text_excerpt,
                ...((array) ($item->publicFetch?->headings ?? [])),
                $item->research?->safe_summary,
                ...((array) ($item->research?->activity_mentions ?? [])),
            ])))->implode(' ');
            $decision = $this->businessRoles->classifyEvidence(
                $combined,
                (string) $domainResults->first()->registrable_domain,
                $job->lane,
            );
            $identity = $this->companyIdentities->resolve($domainResults);
            $completedSameDomainFetch = $domainResults->contains(fn ($result): bool => $result->publicFetch?->status === 'completed'
                && hash_equals(
                    (string) $result->registrable_domain,
                    (string) $result->publicFetch->registrable_domain,
                ));
            if ($completedSameDomainFetch && $decision->candidateEligible && $identity->resolved()) {
                $reviewableDomains++;
            } else {
                $domainsRequiringReview++;
            }
        }
        if ($reviewableDomains < 1) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'public_research_review_required',
                'Public evidence did not establish both a buyer-like business role and a reviewable company identity.',
                [
                    'completed' => $completed,
                    'buyer_identity_ready_domains' => 0,
                    'domains_requiring_review' => $domainsRequiringReview,
                ],
            );
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'completed' => $completed,
            'blocked' => $blocked,
            'buyer_identity_ready_domains' => $reviewableDomains,
            'domains_requiring_review' => $domainsRequiringReview,
        ]);
    }

    private function ingest(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $candidateCount = $job->candidates()->count();
        if ($campaign->automation_mode !== ClientAcquisitionAutomationMode::AutonomousReviewed) {
            if ($candidateCount > 0) {
                return ClientAcquisitionCampaignStageOutcome::completed([
                    'created' => 0, 'candidates' => $candidateCount, 'manual_ingestion' => true, 'retries' => 0,
                ]);
            }

            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'candidate_ingestion_review_required',
                'Protected manual Candidate ingestion is required before the campaign can continue.',
                [
                    'reviewable_results' => $job->searchResults()->whereNull('duplicate_of_id')
                        ->whereNull('prospecting_candidate_id')->where('fetch_status', 'completed')->count(),
                    'candidates' => 0,
                    'automatic_ingestion' => false,
                    'retries' => 0,
                ],
            );
        }
        if (! (bool) config('ai-sales.campaigns.auto_ingest_enabled', false)) {
            return ClientAcquisitionCampaignStageOutcome::blocked(
                'campaign_auto_ingest_disabled',
                'Autonomous-reviewed Candidate ingestion is disabled.',
            );
        }

        $this->features->autoIngest();
        try {
            $this->prospectingFeatures->candidateImport();
        } catch (Throwable) {
            return ClientAcquisitionCampaignStageOutcome::blocked(
                'campaign_candidate_ingestion_policy_blocked',
                'Autonomous-reviewed Candidate ingestion failed a required feature or policy guard.',
            );
        }
        $remaining = max(0, min((int) $campaign->max_candidates_per_run, (int) $job->max_candidates) - $job->candidates()->count());
        $created = 0;
        $reviewRequired = 0;
        $results = $job->searchResults()->whereNull('duplicate_of_id')->whereNull('prospecting_candidate_id')
            ->where('fetch_status', 'completed')->orderBy('rank')->limit(max(100, $remaining * 5))->get()
            ->groupBy('domain_hash')->map->first()->take($remaining);
        foreach ($results as $result) {
            try {
                $this->ingest->handle($result, $actor);
                $created++;
            } catch (ValidationException $exception) {
                $code = collect($exception->errors())->flatten()->first();
                if (is_string($code) && (str_contains($code, 'identity_unresolved_review_required')
                    || str_contains($code, 'buyer_role_not_candidate_eligible'))) {
                    $reviewRequired++;

                    continue;
                }

                return ClientAcquisitionCampaignStageOutcome::blocked(
                    'campaign_candidate_ingestion_policy_blocked',
                    'Autonomous-reviewed Candidate ingestion failed a required DLP, dedupe, evidence or approval guard.',
                    ['created' => $created, 'candidates' => $job->candidates()->count(), 'retries' => 0],
                );
            } catch (Throwable) {
                return ClientAcquisitionCampaignStageOutcome::blocked(
                    'campaign_candidate_ingestion_policy_blocked',
                    'Autonomous-reviewed Candidate ingestion failed a required DLP, dedupe, evidence or approval guard.',
                    ['created' => $created, 'candidates' => $job->candidates()->count(), 'retries' => 0],
                );
            }
        }

        if ($created === 0 && $reviewRequired > 0) {
            return ClientAcquisitionCampaignStageOutcome::requiresAction(
                'public_research_review_required',
                'Buyer role or company identity evidence requires human review before Candidate creation.',
                ['domains_requiring_review' => $reviewRequired, 'automatic_candidates' => 0, 'retries' => 0],
            );
        }

        return ClientAcquisitionCampaignStageOutcome::completed([
            'created' => $created, 'candidates' => $job->candidates()->count(),
            'domains_requiring_review' => $reviewRequired, 'retries' => 0,
        ]);
    }

    private function resolve(AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $counts = ['exact' => 0, 'probable' => 0, 'new' => 0, 'rejected' => 0];
        foreach ($job->candidates()->where('status', ProspectingCandidateStatus::PendingResolution->value)->get() as $candidate) {
            $decision = $this->resolution->evaluate($candidate, $actor);
            $key = match ($decision->outcome->value) {
                'exact_existing' => 'exact', 'probable_existing_review' => 'probable',
                'new_unit_allowed' => 'new', default => 'rejected',
            };
            $counts[$key]++;
        }

        return ClientAcquisitionCampaignStageOutcome::completed($counts);
    }

    private function units(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $created = 0;
        $review = $job->candidates()->whereIn('status', [
            ProspectingCandidateStatus::ExactExistingUnit->value,
            ProspectingCandidateStatus::ProbableExistingReview->value,
        ])->count();
        if ((bool) config('ai-sales.campaigns.auto_create_unit_enabled', false)) {
            foreach ($job->candidates()->where('status', ProspectingCandidateStatus::NewUnitReview->value)
                ->limit((int) $campaign->max_units_per_run)->get() as $candidate) {
                try {
                    $this->resolution->createNewUnitAutonomously($candidate, $campaign, $actor);
                    $created++;
                } catch (PolicyViolation) {
                    $review++;
                }
            }
        } else {
            $review += $job->candidates()->where('status', ProspectingCandidateStatus::NewUnitReview->value)->count();
        }

        return ClientAcquisitionCampaignStageOutcome::completed(['units_created' => $created, 'review_required' => $review]);
    }

    private function matches(AiAgentRun $run): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);
        $matches = \App\Models\UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->where('prospecting_search_job_id', $job->id))->count();
        $review = \App\Models\UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->where('prospecting_search_job_id', $job->id))->whereIn('status', ['suggested', 'reviewed'])->count();

        return ClientAcquisitionCampaignStageOutcome::completed(['matches' => $matches, 'review_required' => $review]);
    }

    private function score(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        if (! (bool) config('ai-sales.campaigns.auto_scoring_enabled', false)) {
            return ClientAcquisitionCampaignStageOutcome::completed(['scored' => 0, 'review_required' => true]);
        }
        $this->features->autoScoring();
        $job = $this->job($run);
        $matches = \App\Models\UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->where('prospecting_search_job_id', $job->id))->limit((int) $campaign->max_candidates_per_run)->get();
        $scored = 0;
        foreach ($matches as $match) {
            $this->scoring->product($actor, $match);
            $this->scoring->priority($actor, $match->businessContext);
            $scored++;
        }

        return ClientAcquisitionCampaignStageOutcome::completed(['scored' => $scored]);
    }

    private function draft(ClientAcquisitionCampaign $campaign, AiAgentRun $run, User $actor): ClientAcquisitionCampaignStageOutcome
    {
        if (! (bool) config('ai-sales.campaigns.auto_draft_enabled', false)) {
            return ClientAcquisitionCampaignStageOutcome::completed(['drafts' => 0, 'review_required' => true]);
        }
        $job = $this->job($run);
        $matches = \App\Models\UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->where('prospecting_search_job_id', $job->id))->where('status', 'approved')
            ->limit((int) $campaign->max_drafts_per_run)->get();
        $created = 0;
        $blocked = 0;
        foreach ($matches as $match) {
            try {
                $this->drafts->create($campaign, $match->businessContext, $match, $actor);
                $created++;
            } catch (PolicyViolation) {
                $blocked++;
            }
        }

        return ClientAcquisitionCampaignStageOutcome::completed(['drafts' => $created, 'policy_blocks' => $blocked]);
    }

    private function digest(AiAgentRun $run): ClientAcquisitionCampaignStageOutcome
    {
        $job = $this->job($run);

        return ClientAcquisitionCampaignStageOutcome::completed([
            'results' => $job->searchResults()->count(),
            'candidates' => $job->candidates()->count(),
            'resolved' => $job->candidates()->whereNotNull('resolved_unit_id')->count(),
        ]);
    }

    private function job(AiAgentRun $run): ProspectingSearchJob
    {
        $jobId = ClientAcquisitionCampaignRunLink::query()->where('ai_agent_run_id', $run->id)
            ->value('prospecting_search_job_id');

        return ProspectingSearchJob::query()->findOrFail($jobId);
    }
}
