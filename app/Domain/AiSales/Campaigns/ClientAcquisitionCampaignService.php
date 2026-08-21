<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignCadence;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignProductRole;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignStatus;
use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\AiRunStepStatus;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\FindBuyers\FindBuyersGeographyService;
use App\Domain\AiSales\Prospecting\BuyerSegmentCatalog;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Models\ClientAcquisitionCampaign;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientAcquisitionCampaignService
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

    public function __construct(
        private readonly ClientAcquisitionCampaignFeatureGuard $features,
        private readonly ClientAcquisitionCampaignAuthorizationService $authorization,
        private readonly ClientAcquisitionCampaignHashes $hashes,
        private readonly ClientAcquisitionCampaignWorkflowRegistry $workflow,
        private readonly ClientAcquisitionCampaignStateMachine $states,
        private readonly GoodProductMappingResolver $goodMappings,
        private readonly FindBuyersGeographyService $geography,
        private readonly BuyerSegmentCatalog $segments,
    ) {}

    public function create(array $input, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::MANAGE);
        $scope = $this->scope($input);
        $this->assertScope($scope, $input['originating_good_id'] ?? null);
        $mode = ClientAcquisitionAutomationMode::from($input['automation_mode'] ?? 'manual');
        $automation = $this->automationSettings($input, $actor, $mode);
        $criteria = $this->criteria($input);
        $criteria = $this->validatedCriteria($criteria);
        $limits = $this->limits($input['limits'] ?? []);

        return DB::transaction(function () use ($input, $actor, $scope, $mode, $automation, $criteria, $limits): ClientAcquisitionCampaign {
            $campaign = ClientAcquisitionCampaign::query()->create([
                'safe_name' => mb_substr(trim((string) $input['safe_name']), 0, 160),
                'safe_objective' => mb_substr(trim((string) $input['safe_objective']), 0, 512),
                'created_by' => $actor->id,
                'owner_user_id' => $actor->id,
                'reviewer_user_id' => $input['reviewer_user_id'] ?? null,
                'originating_good_id' => $input['originating_good_id'] ?? null,
                'purpose' => ProspectingPurpose::BuyerDiscovery,
                'lane' => BusinessLane::Sales,
                'role_code' => UnitRoleCode::ProspectiveCustomer,
                'status' => ClientAcquisitionCampaignStatus::Draft,
                'automation_mode' => $mode,
                'criteria_snapshot' => $criteria,
                'product_scope_hash' => $this->hashes->productScope($scope),
                'criteria_geography_hash' => $this->hashes->criteria($criteria),
                'workflow_code' => ClientAcquisitionCampaignWorkflowRegistry::CODE,
                'workflow_version' => ClientAcquisitionCampaignWorkflowRegistry::VERSION,
                'workflow_hash' => $this->workflow->hash(),
                'policy_version' => 'stage14-v1',
                'policy_hash' => $this->hashes->policy(),
                'disclosure_policy_hash' => $this->hashes->disclosure(),
                'schedule_cadence' => ClientAcquisitionCampaignCadence::from($input['schedule_cadence'] ?? 'manual'),
                'schedule_timezone' => $input['schedule_timezone'] ?? 'Europe/Moscow',
                'next_run_at' => $input['next_run_at'] ?? null,
                ...$limits,
                ...$automation,
            ]);
            $this->syncProducts($campaign, $scope);

            return $this->fresh($campaign);
        }, 3);
    }

    public function update(ClientAcquisitionCampaign $campaign, array $input, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::MANAGE);
        if ($campaign->status === ClientAcquisitionCampaignStatus::Archived
            || $campaign->status === ClientAcquisitionCampaignStatus::Cancelled
            || $campaign->status === ClientAcquisitionCampaignStatus::Running) {
            throw ValidationException::withMessages(['status' => 'This campaign cannot be edited in its current state.']);
        }
        $scope = $this->scope($input, $campaign);
        $originatingGoodId = array_key_exists('originating_good_id', $input)
            ? $input['originating_good_id'] : $campaign->originating_good_id;
        $this->assertScope($scope, $originatingGoodId);
        $mode = ClientAcquisitionAutomationMode::from($input['automation_mode'] ?? $campaign->automation_mode->value);
        $automation = $this->automationSettings([
            'auto_unit_approved' => $input['auto_unit_approved'] ?? $campaign->auto_unit_approved,
            'auto_draft_approved' => $input['auto_draft_approved'] ?? $campaign->auto_draft_approved,
        ], $actor, $mode);
        $criteria = $this->criteria($input, $campaign);
        $criteria = $this->validatedCriteria($criteria, $campaign);
        $limits = $this->limits($input['limits'] ?? [], $campaign);

        return DB::transaction(function () use ($campaign, $input, $scope, $originatingGoodId, $mode, $automation, $criteria, $limits): ClientAcquisitionCampaign {
            ClientAcquisitionCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $campaign->fill([
                'safe_name' => array_key_exists('safe_name', $input)
                    ? mb_substr(trim((string) $input['safe_name']), 0, 160) : $campaign->safe_name,
                'safe_objective' => array_key_exists('safe_objective', $input)
                    ? mb_substr(trim((string) $input['safe_objective']), 0, 512) : $campaign->safe_objective,
                'reviewer_user_id' => array_key_exists('reviewer_user_id', $input)
                    ? $input['reviewer_user_id'] : $campaign->reviewer_user_id,
                'originating_good_id' => $originatingGoodId,
                'automation_mode' => $mode,
                'criteria_snapshot' => $criteria,
                'product_scope_hash' => $this->hashes->productScope($scope),
                'criteria_geography_hash' => $this->hashes->criteria($criteria),
                'workflow_hash' => $this->workflow->hash(),
                'policy_hash' => $this->hashes->policy(),
                'disclosure_policy_hash' => $this->hashes->disclosure(),
                'schedule_cadence' => ClientAcquisitionCampaignCadence::from($input['schedule_cadence'] ?? $campaign->schedule_cadence->value),
                'schedule_timezone' => $input['schedule_timezone'] ?? $campaign->schedule_timezone,
                'next_run_at' => array_key_exists('next_run_at', $input) ? $input['next_run_at'] : $campaign->next_run_at,
                ...$limits,
                ...$automation,
                'status' => $campaign->approved_at ? ClientAcquisitionCampaignStatus::ReviewRequired : $campaign->status,
                'approval_snapshot_hash' => null,
                'approved_by' => null,
                'approved_at' => null,
                'last_block_code' => null,
                'safe_status_summary' => $campaign->approved_at ? 'Material edit invalidated the previous approval.' : null,
                'lock_version' => $campaign->lock_version + 1,
            ])->save();
            $this->syncProducts($campaign, $scope);

            return $this->fresh($campaign);
        }, 3);
    }

    public function submit(ClientAcquisitionCampaign $campaign, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::MANAGE);
        if ((int) $campaign->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['owner' => 'Only the campaign owner may submit it for review.']);
        }
        $this->assertReadyForApproval($campaign);

        return $this->fresh($this->states->transition($campaign, ClientAcquisitionCampaignStatus::ReviewRequired));
    }

    public function approve(ClientAcquisitionCampaign $campaign, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::REVIEW);
        if ($campaign->reviewer_user_id && (int) $campaign->reviewer_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['reviewer' => 'Campaign is assigned to another reviewer.']);
        }
        if ($campaign->automation_mode === ClientAcquisitionAutomationMode::AutonomousReviewed
            && ($campaign->auto_unit_approved || $campaign->auto_draft_approved)) {
            $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::MANAGE_AUTOMATION);
        }
        $this->assertReadyForApproval($campaign);
        $campaign->refresh();
        $approvalHash = $this->hashes->approval($campaign);
        $target = $campaign->schedule_cadence === ClientAcquisitionCampaignCadence::Manual
            ? ClientAcquisitionCampaignStatus::Approved : ClientAcquisitionCampaignStatus::Scheduled;

        return $this->fresh($this->states->transition($campaign, $target, [
            'reviewer_user_id' => $campaign->reviewer_user_id ?: $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_snapshot_hash' => $approvalHash,
            'product_scope_hash' => $this->hashes->persistedProductScope($campaign),
            'criteria_geography_hash' => $this->hashes->criteria($campaign->criteria_snapshot ?? []),
            'workflow_hash' => $this->workflow->hash(),
            'policy_hash' => $this->hashes->policy(),
            'disclosure_policy_hash' => $this->hashes->disclosure(),
            'safe_status_summary' => 'Human-reviewed campaign approval is current.',
        ]));
    }

    public function pause(ClientAcquisitionCampaign $campaign, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);

        return $this->fresh($this->states->transition($campaign, ClientAcquisitionCampaignStatus::Paused, [
            'paused_by' => $actor->id, 'paused_at' => now(), 'safe_status_summary' => 'Paused by an authorized operator.',
        ]));
    }

    public function resume(ClientAcquisitionCampaign $campaign, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);
        if (! $this->hashes->isCurrent($campaign)) {
            return $this->fresh($this->states->transition($campaign, ClientAcquisitionCampaignStatus::ReviewRequired, [
                'approval_snapshot_hash' => null, 'approved_by' => null, 'approved_at' => null,
                'last_block_code' => 'campaign_approval_stale', 'safe_status_summary' => 'Approval became stale.',
            ]));
        }
        $hasActiveRun = $campaign->runLinks()->whereHas('run', fn ($query) => $query->whereIn('status', [
            'queued', 'preparing', 'policy_check', 'ready', 'requires_action', 'processing',
        ]))->exists();
        $target = $hasActiveRun
            ? ClientAcquisitionCampaignStatus::Running
            : ($campaign->schedule_cadence === ClientAcquisitionCampaignCadence::Manual
                ? ClientAcquisitionCampaignStatus::Approved : ClientAcquisitionCampaignStatus::Scheduled);

        return $this->fresh($this->states->transition($campaign, $target, [
            'paused_by' => null, 'paused_at' => null, 'last_block_code' => null,
            'safe_status_summary' => 'Resumed by an authorized operator.',
        ]));
    }

    public function cancel(ClientAcquisitionCampaign $campaign, User $actor): ClientAcquisitionCampaign
    {
        $this->features->campaigns();
        $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::OPERATE);
        if ($campaign->status === ClientAcquisitionCampaignStatus::Cancelled) {
            return $this->fresh($campaign);
        }

        return DB::transaction(function () use ($campaign): ClientAcquisitionCampaign {
            $cancelled = $this->states->transition($campaign, ClientAcquisitionCampaignStatus::Cancelled, [
                'cancelled_at' => now(), 'next_run_at' => null,
                'safe_status_summary' => 'Cancelled by an authorized operator.',
            ]);
            $runIds = $cancelled->runLinks()->pluck('ai_agent_run_id');
            \App\Models\AiAgentRun::query()->whereIn('id', $runIds)
                ->whereIn('status', ['queued', 'preparing', 'policy_check', 'ready', 'sent', 'requires_action', 'processing'])
                ->update([
                    'status' => AiRunStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'safe_error_code' => 'campaign_cancelled',
                    'safe_error_summary' => 'Campaign run was cancelled by an authorized operator.',
                    'updated_at' => now(),
                ]);
            \App\Models\AiAgentRunStep::query()->whereIn('ai_agent_run_id', $runIds)
                ->whereIn('status', ['queued', 'ready', 'sent', 'processing', 'requires_action'])
                ->update([
                    'status' => AiRunStepStatus::Cancelled->value,
                    'safe_error_code' => 'campaign_cancelled',
                    'safe_error_summary' => 'Campaign stage was cancelled.',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->fresh($cancelled);
        }, 3);
    }

    public function nextRunAt(ClientAcquisitionCampaign $campaign): ?\Illuminate\Support\Carbon
    {
        $base = ($campaign->next_run_at ?: now())->copy()->timezone($campaign->schedule_timezone);

        return match ($campaign->schedule_cadence) {
            ClientAcquisitionCampaignCadence::Manual => null,
            ClientAcquisitionCampaignCadence::Daily => $base->addDay(),
            ClientAcquisitionCampaignCadence::Weekly => $base->addWeek(),
            ClientAcquisitionCampaignCadence::Monthly => $base->addMonthNoOverflow(),
        };
    }

    /** @return array{primary: ?int, additional: list<int>, exclude: list<int>} */
    private function scope(array $input, ?ClientAcquisitionCampaign $campaign = null): array
    {
        $current = $campaign ? $campaign->products()->get(['products.id'])->groupBy('pivot.role') : collect();
        $primary = array_key_exists('primary_product_id', $input)
            ? ($input['primary_product_id'] ? (int) $input['primary_product_id'] : null)
            : $current->get('primary')?->first()?->id;
        $additional = array_key_exists('additional_product_ids', $input)
            ? collect($input['additional_product_ids'])->map('intval')->unique()->values()->all()
            : $current->get('additional', collect())->pluck('id')->map('intval')->all();
        $exclude = array_key_exists('excluded_product_ids', $input)
            ? collect($input['excluded_product_ids'])->map('intval')->unique()->values()->all()
            : $current->get('exclude', collect())->pluck('id')->map('intval')->all();

        if ($primary && in_array($primary, $additional, true)) {
            throw ValidationException::withMessages(['additional_product_ids' => 'Primary Product cannot be duplicated.']);
        }
        if (array_intersect(array_filter([$primary, ...$additional]), $exclude) !== []) {
            throw ValidationException::withMessages(['excluded_product_ids' => 'A Product cannot be included and excluded.']);
        }

        return ['primary' => $primary, 'additional' => $additional, 'exclude' => $exclude];
    }

    private function assertScope(array $scope, mixed $goodId): void
    {
        if (! $scope['primary']) {
            throw ValidationException::withMessages(['primary_product_id' => 'A primary published Product is required.']);
        }
        $ids = array_values(array_unique(array_filter([$scope['primary'], ...$scope['additional'], ...$scope['exclude']])));
        $published = Product::query()->without(['category', 'manufacturers'])
            ->whereIn('id', $ids)->where('is_published', true)->count();
        if ($published !== count($ids)) {
            throw ValidationException::withMessages(['product_ids' => 'Campaign scope accepts only published Products.']);
        }
        if ($goodId && $this->goodMappings->stateForExplicitProduct((int) $goodId, (int) $scope['primary'])->value !== 'mapped') {
            throw ValidationException::withMessages(['originating_good_id' => 'Good must map deterministically to the primary Product.']);
        }
    }

    private function criteria(array $input, ?ClientAcquisitionCampaign $campaign = null): array
    {
        $criteria = $input['criteria'] ?? ($campaign?->criteria_snapshot ?? []);
        foreach (['country_id', 'region_id', 'city_id'] as $key) {
            if (array_key_exists($key, $input)) {
                $criteria[$key] = $input[$key] ? (int) $input[$key] : null;
            }
        }

        return collect($criteria)->only([
            'country_id', 'region_id', 'city_id', 'segments', 'industries', 'categories',
            'excluded_categories', 'company_types', 'max_domains', 'max_page_fetch_attempts',
            'max_results_per_query',
        ])->map(function ($value) {
            if (is_array($value)) {
                return collect($value)->filter(fn ($item) => is_scalar($item))->map(
                    fn ($item) => is_string($item) ? mb_substr(trim($item), 0, 120) : $item,
                )->unique()->take(25)->values()->all();
            }

            return $value;
        })->sortKeys()->all();
    }

    private function validatedCriteria(array $criteria, ?ClientAcquisitionCampaign $campaign = null): array
    {
        $geography = $this->geography->validate(
            isset($criteria['country_id']) ? (int) $criteria['country_id'] : null,
            isset($criteria['region_id']) ? (int) $criteria['region_id'] : null,
            isset($criteria['city_id']) ? (int) $criteria['city_id'] : null,
        );
        foreach (['country_id', 'region_id', 'city_id'] as $key) {
            $criteria[$key] = $geography[$key];
        }
        $segments = array_values(array_filter((array) ($criteria['segments'] ?? []), 'is_string'));
        $legacy = collect((array) (($campaign?->criteria_snapshot ?? [])['segments'] ?? []));
        $this->segments->assertAllowed(collect($segments)->reject(fn (string $value): bool => $legacy->contains($value))->values()->all());
        $criteria['segments'] = $segments;

        return collect($criteria)->sortKeys()->all();
    }

    private function limits(array $input, ?ClientAcquisitionCampaign $campaign = null): array
    {
        $limits = [];
        foreach (self::LIMIT_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $limits[$field] = str_starts_with($field, 'max_cost_')
                    ? max(0, round((float) $input[$field], 4))
                    : max(0, (int) $input[$field]);
            } elseif ($campaign) {
                $limits[$field] = $campaign->{$field};
            } else {
                $limits[$field] = $field === 'max_active_runs' ? 1 : 0;
            }
        }

        $limits['max_active_runs'] = min(1, max(1, (int) $limits['max_active_runs']));

        return $limits;
    }

    private function automationSettings(array $input, User $actor, ClientAcquisitionAutomationMode $mode): array
    {
        $autoUnit = $mode === ClientAcquisitionAutomationMode::AutonomousReviewed
            && (bool) ($input['auto_unit_approved'] ?? false);
        $autoDraft = $mode === ClientAcquisitionAutomationMode::AutonomousReviewed
            && (bool) ($input['auto_draft_approved'] ?? false);
        if ($autoUnit || $autoDraft) {
            $this->authorization->authorize($actor, ClientAcquisitionCampaignAuthorizationService::MANAGE_AUTOMATION);
        }

        return [
            'auto_unit_policy_code' => AutonomousUnitCreationPolicy::CODE,
            'auto_unit_policy_version' => AutonomousUnitCreationPolicy::VERSION,
            'auto_unit_approved' => $autoUnit,
            'auto_draft_policy_code' => AutonomousOutreachDraftPolicy::CODE,
            'auto_draft_policy_version' => AutonomousOutreachDraftPolicy::VERSION,
            'auto_draft_approved' => $autoDraft,
        ];
    }

    private function assertReadyForApproval(ClientAcquisitionCampaign $campaign): void
    {
        if ($campaign->products()->wherePivot('role', ClientAcquisitionCampaignProductRole::Primary->value)->count() !== 1) {
            throw ValidationException::withMessages(['products' => 'Campaign approval requires exactly one primary Product.']);
        }
        if ((int) $campaign->max_runs_per_day < 1 || (int) $campaign->max_runs_per_month < 1
            || (int) $campaign->max_candidates_per_run < 1 || (int) $campaign->max_requests_per_run < 1) {
            throw ValidationException::withMessages(['limits' => 'Fail-closed campaign run, Candidate and request limits must be positive.']);
        }
        if ($campaign->schedule_cadence !== ClientAcquisitionCampaignCadence::Manual && ! $campaign->next_run_at) {
            throw ValidationException::withMessages(['next_run_at' => 'Scheduled campaigns require a next run time.']);
        }
        if ($campaign->workflow_code !== ClientAcquisitionCampaignWorkflowRegistry::CODE
            || ! hash_equals($this->workflow->hash(), (string) $campaign->workflow_hash)
            || ! hash_equals($this->hashes->policy(), (string) $campaign->policy_hash)
            || ! hash_equals($this->hashes->disclosure(), (string) $campaign->disclosure_policy_hash)
            || ! hash_equals($this->hashes->persistedProductScope($campaign), (string) $campaign->product_scope_hash)
            || ! hash_equals($this->hashes->criteria($campaign->criteria_snapshot ?? []), (string) $campaign->criteria_geography_hash)) {
            throw ValidationException::withMessages(['hashes' => 'Campaign definition or policy hashes are stale.']);
        }
    }

    private function syncProducts(ClientAcquisitionCampaign $campaign, array $scope): void
    {
        $sync = [(int) $scope['primary'] => ['role' => 'primary', 'source_origin' => 'human_review']];
        foreach ($scope['additional'] as $id) {
            $sync[(int) $id] = ['role' => 'additional', 'source_origin' => 'human_review'];
        }
        foreach ($scope['exclude'] as $id) {
            $sync[(int) $id] = ['role' => 'exclude', 'source_origin' => 'human_review'];
        }
        $campaign->products()->sync($sync);
    }

    private function fresh(ClientAcquisitionCampaign $campaign): ClientAcquisitionCampaign
    {
        return $campaign->fresh([
            'owner:id,name', 'reviewer:id,name', 'originatingGood:id,name',
            'products' => fn ($query) => $query->without(['category', 'manufacturers'])
                ->select(['products.id', 'products.rus', 'products.eng']),
        ]);
    }
}
