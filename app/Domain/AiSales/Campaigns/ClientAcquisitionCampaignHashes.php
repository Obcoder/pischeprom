<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\ClientAcquisitionCampaign;

final class ClientAcquisitionCampaignHashes
{
    private const LIMIT_FIELDS = [
        'max_active_runs', 'max_runs_per_day', 'max_runs_per_month',
        'max_search_requests_per_run', 'max_search_requests_per_day', 'max_search_requests_per_month',
        'max_research_pages_per_run', 'max_candidates_per_run', 'max_units_per_run',
        'max_units_per_day', 'max_units_per_month', 'max_drafts_per_run', 'max_drafts_per_day',
        'max_drafts_per_month', 'max_requests_per_run', 'max_requests_per_day',
        'max_requests_per_month', 'max_tokens_per_run', 'max_tokens_per_day', 'max_tokens_per_month',
        'max_cost_rub_per_run', 'max_cost_rub_per_day', 'max_cost_rub_per_month',
    ];

    public function __construct(private readonly ClientAcquisitionCampaignWorkflowRegistry $workflow) {}

    /** @param array{primary: ?int, additional: list<int>, exclude: list<int>} $scope */
    public function productScope(array $scope): string
    {
        return AiCanonicalJson::hash([
            'primary' => $scope['primary'],
            'additional' => collect($scope['additional'])->map('intval')->sort()->values()->all(),
            'exclude' => collect($scope['exclude'])->map('intval')->sort()->values()->all(),
        ]);
    }

    public function persistedProductScope(ClientAcquisitionCampaign $campaign): string
    {
        $scope = $campaign->products()->get(['products.id'])->groupBy('pivot.role');

        return $this->productScope([
            'primary' => $scope->get('primary')?->first()?->id,
            'additional' => $scope->get('additional', collect())->pluck('id')->map('intval')->all(),
            'exclude' => $scope->get('exclude', collect())->pluck('id')->map('intval')->all(),
        ]);
    }

    public function criteria(array $criteria): string
    {
        return AiCanonicalJson::hash($criteria);
    }

    public function policy(): string
    {
        return AiCanonicalJson::hash([
            'version' => 'stage14-v1',
            'unit_policy' => [
                'code' => AutonomousUnitCreationPolicy::CODE,
                'version' => AutonomousUnitCreationPolicy::VERSION,
                'minimum_independent_sources' => (int) config('ai-sales.campaigns.policies.auto_unit.minimum_independent_sources', 2),
            ],
            'draft_policy' => [
                'code' => AutonomousOutreachDraftPolicy::CODE,
                'version' => AutonomousOutreachDraftPolicy::VERSION,
                'minimum_relevance' => (int) config('ai-sales.campaigns.policies.auto_draft.minimum_product_relevance', 60),
                'minimum_confidence' => (int) config('ai-sales.campaigns.policies.auto_draft.minimum_confidence', 70),
            ],
            'retries' => 0,
            'failovers' => 0,
            'dispatch' => false,
        ]);
    }

    public function disclosure(): string
    {
        return AiCanonicalJson::hash([
            'classification' => config('ai-sales.policy_versions.classification_registry'),
            'disclosure' => config('ai-sales.policy_versions.disclosure'),
            'contour' => config('ai-sales.policy_versions.processing_contour'),
            'tool_dlp' => config('ai-sales.policy_versions.tool_dlp'),
            'find_buyers' => config('ai-sales.policy_versions.find_buyers_disclosure'),
            'outreach_dlp' => config('ai-sales.policy_versions.outreach_dlp'),
            'lane' => 'sales',
            'recipient_pii_external' => false,
        ]);
    }

    public function approval(ClientAcquisitionCampaign $campaign): string
    {
        $limits = [];
        foreach (self::LIMIT_FIELDS as $field) {
            $limits[$field] = str_starts_with($field, 'max_cost_')
                ? (string) $campaign->{$field}
                : (int) $campaign->{$field};
        }

        return AiCanonicalJson::hash([
            'campaign_public_id' => $campaign->public_id,
            'purpose' => $campaign->purpose->value,
            'lane' => $campaign->lane->value,
            'role_code' => $campaign->role_code->value,
            'product_scope_hash' => $this->persistedProductScope($campaign),
            'criteria_geography_hash' => $this->criteria($campaign->criteria_snapshot ?? []),
            'schedule' => [
                'cadence' => $campaign->schedule_cadence->value,
                'timezone' => $campaign->schedule_timezone,
                'next_run_at' => $campaign->next_run_at?->toISOString(),
            ],
            'automation_mode' => $campaign->automation_mode->value,
            'limits' => $limits,
            'workflow_code' => ClientAcquisitionCampaignWorkflowRegistry::CODE,
            'workflow_version' => ClientAcquisitionCampaignWorkflowRegistry::VERSION,
            'workflow_hash' => $this->workflow->hash(),
            'policy_hash' => $this->policy(),
            'disclosure_policy_hash' => $this->disclosure(),
            'auto_unit' => [
                'approved' => $campaign->auto_unit_approved,
                'code' => $campaign->auto_unit_policy_code,
                'version' => $campaign->auto_unit_policy_version,
            ],
            'auto_draft' => [
                'approved' => $campaign->auto_draft_approved,
                'code' => $campaign->auto_draft_policy_code,
                'version' => $campaign->auto_draft_policy_version,
            ],
        ]);
    }

    public function isCurrent(ClientAcquisitionCampaign $campaign): bool
    {
        return is_string($campaign->approval_snapshot_hash)
            && preg_match('/^[a-f0-9]{64}$/D', $campaign->approval_snapshot_hash) === 1
            && hash_equals($campaign->approval_snapshot_hash, $this->approval($campaign));
    }
}
