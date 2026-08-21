<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignService;
use App\Models\ClientAcquisitionCampaign;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class Stage14TestCase extends Stage13TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.autonomous_campaigns_enabled' => true,
            'ai-sales.campaigns.enabled' => true,
            'ai-sales.campaigns.scheduler_enabled' => false,
            'ai-sales.campaigns.live_search_enabled' => false,
            'ai-sales.campaigns.live_research_enabled' => false,
            'ai-sales.campaigns.auto_ingest_enabled' => false,
            'ai-sales.campaigns.auto_create_unit_enabled' => true,
            'ai-sales.campaigns.auto_scoring_enabled' => true,
            'ai-sales.campaigns.auto_draft_enabled' => true,
            'ai-sales.campaigns.notifications_enabled' => false,
            'ai-sales.campaigns.synthetic_fixture_mode' => false,
            'ai-sales.campaigns.limits.scheduler_batch' => 5,
            'ai-sales.campaigns.limits.max_active_runs' => 2,
            'ai-sales.campaigns.limits.max_runs_per_day' => 10,
            'ai-sales.campaigns.limits.max_runs_per_month' => 50,
            'ai-sales.campaigns.limits.max_search_requests_per_run' => 10,
            'ai-sales.campaigns.limits.max_search_results_per_run' => 200,
            'ai-sales.campaigns.limits.max_research_pages_per_run' => 10,
            'ai-sales.campaigns.limits.max_domains_per_run' => 10,
            'ai-sales.campaigns.limits.max_candidates_per_run' => 50,
            'ai-sales.campaigns.limits.global_units_per_day' => 5,
            'ai-sales.campaigns.limits.global_units_per_month' => 25,
            'ai-sales.campaigns.limits.global_drafts_per_day' => 5,
            'ai-sales.campaigns.limits.global_drafts_per_month' => 25,
            'ai-sales.prospecting.auto_create_unit' => true,
            'ai-sales.outreach.dispatch_enabled' => false,
            'ai-sales.outreach.provider_send_enabled' => false,
            'ai-sales.outreach.auto_followup_enabled' => false,
            'ai-sales.outreach.auto_send_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.limits.max_retries' => 0,
        ]);
    }

    protected function campaignUser(bool $admin = false, array $extra = []): User
    {
        $user = $this->prospectingUser(['sales'], array_values(array_unique([
            'ai_sales.scoring.view', 'ai_sales.scoring.recalculate',
            'ai_sales.outreach.view', 'ai_sales.outreach.draft', 'ai_sales.outreach.review',
            'ai_sales.outreach.claims.review', 'ai_sales.communication_suppressions.manage',
            'ai_sales.campaigns.view', 'ai_sales.campaigns.manage', 'ai_sales.campaigns.review',
            'ai_sales.campaigns.operate', 'ai_sales.campaigns.automation.manage',
            'ai_sales.campaigns.metrics.view',
            ...$extra,
        ])));
        if ($admin) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'crm']);
            $user->assignRole($role);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $user;
    }

    protected function campaignProduct(string $name = 'Stage 14 Product'): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => $name.' '.uniqid(),
            'eng' => $name,
            'is_published' => true,
        ]);
    }

    protected function campaignPayload(?Product $product = null, array $overrides = []): array
    {
        $product ??= $this->campaignProduct();
        $limits = array_replace($this->campaignLimits(), $overrides['limits'] ?? []);
        unset($overrides['limits']);

        return [
            'safe_name' => 'Stage 14 bounded campaign '.uniqid(),
            'safe_objective' => 'Repository-owned Product-first buyer discovery.',
            'primary_product_id' => $product->id,
            'automation_mode' => 'manual',
            'schedule_cadence' => 'manual',
            'criteria' => [
                'segments' => ['archetype:food_manufacturer'],
                'max_domains' => 3,
                'max_page_fetch_attempts' => 2,
                'max_results_per_query' => 5,
            ],
            'limits' => $limits,
            ...$overrides,
        ];
    }

    protected function campaign(User $actor, ?Product $product = null, array $overrides = []): ClientAcquisitionCampaign
    {
        return app(ClientAcquisitionCampaignService::class)->create(
            $this->campaignPayload($product, $overrides),
            $actor,
        );
    }

    protected function approvedCampaign(User $actor, ?Product $product = null, array $overrides = []): ClientAcquisitionCampaign
    {
        $service = app(ClientAcquisitionCampaignService::class);
        $campaign = $this->campaign($actor, $product, $overrides);
        $campaign = $service->submit($campaign, $actor);

        return $service->approve($campaign, $actor);
    }

    protected function campaignLimits(): array
    {
        return [
            'max_active_runs' => 1, 'max_runs_per_day' => 2, 'max_runs_per_month' => 10,
            'max_search_requests_per_run' => 3, 'max_search_requests_per_day' => 6, 'max_search_requests_per_month' => 30,
            'max_research_pages_per_run' => 3, 'max_candidates_per_run' => 10,
            'max_units_per_run' => 1, 'max_units_per_day' => 2, 'max_units_per_month' => 10,
            'max_drafts_per_run' => 1, 'max_drafts_per_day' => 2, 'max_drafts_per_month' => 10,
            'max_requests_per_run' => 10, 'max_requests_per_day' => 20, 'max_requests_per_month' => 100,
            'max_tokens_per_run' => 4000, 'max_tokens_per_day' => 8000, 'max_tokens_per_month' => 40000,
            'max_cost_rub_per_run' => 10, 'max_cost_rub_per_day' => 20, 'max_cost_rub_per_month' => 100,
        ];
    }
}
