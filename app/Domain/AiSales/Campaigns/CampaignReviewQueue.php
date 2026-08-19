<?php

namespace App\Domain\AiSales\Campaigns;

use App\Models\AiAgentRunStep;
use App\Models\ClientAcquisitionCampaign;
use App\Models\CommunicationPermission;
use App\Models\OutreachDraft;
use App\Models\ProspectingCandidate;
use App\Models\ProspectingSearchQuery;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Carbon;

final class CampaignReviewQueue
{
    public const CATEGORIES = [
        'query_plan_review', 'candidate_ingestion_review', 'candidate_duplicate_review', 'new_unit_review',
        'product_match_review', 'outreach_content_review', 'outreach_claim_review',
        'permission_review', 'policy_block', 'provider_error', 'budget_block',
    ];

    public function __construct(private readonly ClientAcquisitionCampaignAuthorizationService $authorization) {}

    /** @return list<array<string, mixed>> */
    public function forCampaign(ClientAcquisitionCampaign $campaign, User $actor, int $limit = 100): array
    {
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::VIEW);
        if (! $this->authorization->canAccess($actor, $campaign)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Campaign review queue is not authorized.');
        }
        $limit = max(1, min(200, $limit));
        $jobIds = $campaign->runLinks()->whereNotNull('prospecting_search_job_id')->pluck('prospecting_search_job_id');
        $runIds = $campaign->runLinks()->pluck('ai_agent_run_id');
        $linksByJob = $campaign->runLinks()->with('run:id,public_id')->whereNotNull('prospecting_search_job_id')
            ->get()->keyBy('prospecting_search_job_id');
        $linksByRun = $campaign->runLinks()->with('run:id,public_id')->get()->keyBy('ai_agent_run_id');
        $items = collect();

        ProspectingSearchQuery::query()->whereIn('prospecting_search_job_id', $jobIds)
            ->where('plan_status', 'review_required')->limit($limit)->get()
            ->each(fn ($row) => $items->push($this->item(
                $campaign, 'query_plan_review', 'prospecting_search_query', $row->id,
                $row->prospecting_search_job_id, null, 'query_plan_review_required',
                'Review and approve the server-owned query plan.', $row->created_at,
                ['run_id' => $linksByJob->get($row->prospecting_search_job_id)?->run?->public_id],
            )));
        ProspectingCandidate::query()->whereIn('prospecting_search_job_id', $jobIds)
            ->whereIn('status', ['probable_existing_review', 'new_unit_review'])->with('products')->limit($limit)->get()
            ->each(function ($row) use ($campaign, $items, $linksByJob): void {
                $category = $row->status->value === 'probable_existing_review' ? 'candidate_duplicate_review' : 'new_unit_review';
                $items->push($this->item($campaign, $category, 'prospecting_candidate', $row->id,
                    $row->prospecting_search_job_id, $row->resolved_unit_id, $row->resolution_reason_code ?: $category,
                    $category === 'new_unit_review' ? 'Review Candidate before Unit creation.' : 'Resolve the possible duplicate.',
                    $row->created_at, [
                        'run_id' => $linksByJob->get($row->prospecting_search_job_id)?->run?->public_id,
                        'product_id' => $row->products->first()?->product_id,
                        'safe_evidence' => ['source_count' => (int) $row->source_count],
                        'confidence' => collect($row->confidence_components ?? [])
                            ->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (int) $value)->avg(),
                    ]));
            });
        UnitProductMatch::query()->whereHas('candidateProduct.candidate', fn ($query) => $query
            ->whereIn('prospecting_search_job_id', $jobIds))->whereIn('status', ['suggested', 'reviewed'])
            ->with(['candidateProduct.candidate', 'relevanceSnapshots' => fn ($query) => $query->whereNull('stale_at')->whereNull('superseded_at')->latest('id')])
            ->limit($limit)->get()
            ->each(function ($row) use ($campaign, $items, $linksByJob): void {
                $candidate = $row->candidateProduct?->candidate;
                $score = $row->relevanceSnapshots->first();
                $items->push($this->item($campaign, 'product_match_review', 'unit_product_match', $row->id,
                    null, $row->unit_id, 'product_match_review_required', 'Review Product relevance evidence.',
                    $row->created_at, [
                        'run_id' => $linksByJob->get($candidate?->prospecting_search_job_id)?->run?->public_id,
                        'context_id' => $row->unit_business_context_id,
                        'product_id' => $row->product_id,
                        'safe_evidence' => ['reference_hash' => $row->evidence_hash],
                        'score' => $score?->effective_score,
                        'confidence' => $score?->confidence,
                    ]));
            });
        OutreachDraft::query()->whereHas('productMatch.candidateProduct.candidate', fn ($query) => $query
            ->whereIn('prospecting_search_job_id', $jobIds))->where('status', 'review_required')
            ->with('productMatch.candidateProduct.candidate')->limit($limit)->get()
            ->each(function ($row) use ($campaign, $items, $linksByJob): void {
                $candidate = $row->productMatch?->candidateProduct?->candidate;
                $common = [
                    'run_id' => $linksByJob->get($candidate?->prospecting_search_job_id)?->run?->public_id,
                    'context_id' => $row->unit_business_context_id,
                    'product_id' => $row->productMatch?->product_id,
                    'safe_evidence' => ['evidence_hash' => $row->evidence_hash],
                ];
                $items->push($this->item($campaign, 'outreach_content_review', 'outreach_draft', $row->id,
                    null, $row->unit_id, 'outreach_content_review_required', 'Review draft content; dispatch remains blocked.',
                    $row->created_at, $common));
                if ($row->currentRevision()?->claims()->where('review_status', 'pending')->exists()) {
                    $items->push($this->item($campaign, 'outreach_claim_review', 'outreach_draft', $row->id,
                        null, $row->unit_id, 'outreach_claim_review_required', 'Review evidence-backed claims.',
                        $row->created_at, $common));
                }
            });
        CommunicationPermission::query()->whereIn('unit_id', ProspectingCandidate::query()
            ->whereIn('prospecting_search_job_id', $jobIds)->whereNotNull('resolved_unit_id')->select('resolved_unit_id'))
            ->where('status', 'pending_review')->limit($limit)->get()
            ->each(fn ($row) => $items->push($this->item($campaign, 'permission_review', 'communication_permission', $row->id,
                null, $row->unit_id, 'permission_review_required', 'Human permission review is required.',
                $row->created_at, ['context_id' => $row->unit_business_context_id])));
        AiAgentRunStep::query()->whereIn('ai_agent_run_id', $runIds)
            ->whereIn('status', ['requires_action', 'blocked', 'failed'])->limit($limit)->get()
            ->each(function ($row) use ($campaign, $items, $linksByRun): void {
                $code = (string) ($row->safe_error_code ?: 'policy_block');
                $candidateIngestionReview = $code === 'candidate_ingestion_review_required';
                $category = $candidateIngestionReview ? 'candidate_ingestion_review'
                    : (str_contains($code, 'budget') ? 'budget_block'
                    : (str_contains($code, 'provider') || str_contains($code, 'search') ? 'provider_error' : 'policy_block'));
                $link = $linksByRun->get($row->ai_agent_run_id);
                $items->push($this->item($campaign, $category, 'ai_agent_run_step', $row->id,
                    $candidateIngestionReview ? $link?->prospecting_search_job_id : null,
                    null, $code, $candidateIngestionReview
                        ? 'Use the protected manual Candidate ingestion action.'
                        : 'Review the safe campaign-stage outcome.', $row->created_at, [
                            'run_id' => $link?->run?->public_id,
                            'step' => $row->sequence,
                            'safe_evidence' => $row->normalized_output_metadata,
                        ]));
            });

        return $items->sortBy(fn (array $item) => $item['created_at'])->take($limit)->values()->all();
    }

    private function item(
        ClientAcquisitionCampaign $campaign,
        string $category,
        string $sourceType,
        int $sourceId,
        ?int $jobId,
        ?int $unitId,
        string $reason,
        string $nextAction,
        mixed $createdAt,
        array $context = [],
    ): array {
        $created = $createdAt ? Carbon::parse($createdAt) : now();
        $ageMinutes = max(0, (int) $created->diffInMinutes(now()));

        return [
            'category' => $category,
            'campaign_id' => $campaign->public_id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'search_job_id' => $jobId,
            'unit_id' => $unitId,
            'unit_business_context_id' => $context['context_id'] ?? null,
            'product_id' => $context['product_id'] ?? null,
            'run_id' => $context['run_id'] ?? null,
            'step' => $context['step'] ?? null,
            'lane' => 'sales',
            'reason_code' => mb_substr($reason, 0, 96),
            'next_permitted_action' => $nextAction,
            'safe_evidence' => $context['safe_evidence'] ?? null,
            'score' => $context['score'] ?? null,
            'confidence' => $context['confidence'] ?? null,
            'age_minutes' => $ageMinutes,
            'sla_status' => $ageMinutes >= 1440 ? 'overdue' : ($ageMinutes >= 240 ? 'due_soon' : 'within_sla'),
            'owner_user_id' => $campaign->owner_user_id,
            'reviewer_user_id' => $campaign->reviewer_user_id,
            'created_at' => $created->toIso8601String(),
        ];
    }
}
