<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Campaigns\CampaignReviewQueue;
use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignMetrics;
use App\Domain\AiSales\Campaigns\Contracts\ClientAcquisitionCampaignStageProcessorInterface;
use App\Domain\AiSales\Campaigns\DefaultClientAcquisitionCampaignStageProcessor;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Models\Entity;
use App\Models\Sending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class Stage14SyntheticAndArchitectureTest extends Stage14TestCase
{
    public function test_complete_repository_owned_synthetic_campaign_rolls_back_with_zero_egress(): void
    {
        $exit = Artisan::call('ai-sales:run-synthetic-client-acquisition-campaign');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        foreach ([
            'APP_ENV=testing', 'DB_CONNECTION=sqlite', 'DB_DRIVER=sqlite',
            'campaigns=1 runs=1', 'candidates=3 units_auto_created=1 entities=0',
            'emails=0 live_http=0 provider_sends=0', 'idempotent_rerun=yes',
            'budget_block=yes suppression_block=yes dual_lane_isolation=yes dispatch_blocked=yes',
            'live_yandex=0 live_timeweb=0 external_http=0 emails=0 retries=0 failovers=0 entities=0',
            'all fictional rows rolled back',
        ] as $proof) {
            $this->assertStringContainsString($proof, $output);
        }

        $this->assertDatabaseCount('ai_sales_campaigns', 0);
        $this->assertDatabaseCount('ai_sales_campaign_run_links', 0);
        $this->assertDatabaseCount('prospecting_candidates', 0);
        $this->assertSame(0, Entity::query()->without(['buildings', 'classification', 'country'])->count());
        $this->assertSame(0, Sending::query()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_synthetic_command_rejects_default_mysql_without_opening_it(): void
    {
        $original = config('database.default');
        try {
            config()->set('database.default', 'mysql');
            $this->artisan('ai-sales:run-synthetic-client-acquisition-campaign')
                ->expectsOutputToContain('DB_CONNECTION=mysql')
                ->expectsOutputToContain('default MySQL is never accepted')
                ->assertFailed();
        } finally {
            config()->set('database.default', $original);
        }

        Http::assertNothingSent();
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_runtime_binding_uses_real_fixed_processor_and_ui_has_no_live_or_send_action(): void
    {
        $this->assertInstanceOf(
            DefaultClientAcquisitionCampaignStageProcessor::class,
            app(ClientAcquisitionCampaignStageProcessorInterface::class),
        );
        $workflow = file_get_contents(app_path('Domain/AiSales/Campaigns/ClientAcquisitionCampaignWorkflowRegistry.php'));
        $ui = file_get_contents(resource_path('js/Components/AiSales/ClientAcquisitionCampaignDashboard.vue'));
        $routes = file_get_contents(base_path('routes/api.php'));

        $this->assertSame(14, substr_count($workflow, "'sequence' =>"));
        $this->assertStringContainsString("'provider_native_tools' => false", $workflow);
        $this->assertStringNotContainsString('provider_native', $ui);
        $this->assertDoesNotMatchRegularExpression('/axios\.(?:post|put|patch)\([^\n]*(?:send|dispatch|provider)/i', $ui);
        $this->assertStringNotContainsString('campaigns/{clientAcquisitionCampaign}/send', $routes);
        $this->assertContains('budget_block', CampaignReviewQueue::CATEGORIES);
    }

    public function test_all_code_owned_campaign_defaults_are_off_or_zero(): void
    {
        $config = require config_path('ai-sales.php');
        $this->assertFalse($config['autonomous_campaigns_enabled']);
        foreach (['enabled', 'scheduler_enabled', 'live_search_enabled', 'live_research_enabled', 'auto_ingest_enabled',
            'auto_create_unit_enabled', 'auto_scoring_enabled', 'auto_draft_enabled', 'notifications_enabled'] as $flag) {
            $this->assertFalse($config['campaigns'][$flag], $flag);
        }
        foreach ($config['campaigns']['limits'] as $limit) {
            $this->assertSame(0, $limit);
        }
        $this->assertFalse($config['outreach']['dispatch_enabled']);
        $this->assertFalse($config['outreach']['provider_send_enabled']);
        $this->assertFalse($config['outreach']['auto_followup_enabled']);
        $this->assertSame(0, $config['limits']['max_retries']);
        $this->assertFalse($config['provider_failover_enabled']);
    }

    public function test_scheduler_command_is_safe_dry_run_by_default(): void
    {
        $this->artisan('ai-sales:run-due-campaigns')
            ->expectsOutput('mode=dry-run due=0 dispatched=0 blocked=0 retries=0 failovers=0')
            ->assertSuccessful();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_review_queue_and_metrics_are_safe_source_projections_with_lane_scoped_access(): void
    {
        $actor = $this->campaignUser();
        $campaign = $this->approvedCampaign($actor);
        $run = app(StartClientAcquisitionCampaignRun::class)->handle($campaign, $actor, 'review-projection');
        $step = $run->steps()->orderBy('sequence')->first();
        $step->update([
            'status' => 'blocked',
            'safe_error_code' => 'campaign_budget_block',
            'safe_error_summary' => 'Safe bounded budget review.',
            'normalized_output_metadata' => ['safe_counter' => 1],
            'completed_at' => now(),
        ]);
        $run->update([
            'status' => 'budget_exceeded',
            'safe_error_code' => 'campaign_budget_block',
            'safe_error_summary' => 'Safe bounded budget review.',
            'completed_at' => now(),
        ]);

        $items = app(CampaignReviewQueue::class)->forCampaign($campaign->fresh(), $actor);
        $this->assertCount(1, $items);
        $this->assertSame('budget_block', $items[0]['category']);
        $this->assertSame($run->public_id, $items[0]['run_id']);
        $this->assertSame(1, $items[0]['step']);
        $this->assertSame(['safe_counter' => 1], $items[0]['safe_evidence']);
        $this->assertArrayHasKey('age_minutes', $items[0]);
        $this->assertArrayHasKey('sla_status', $items[0]);
        $this->assertArrayNotHasKey('raw_body', $items[0]);
        $metrics = app(ClientAcquisitionCampaignMetrics::class)->get($campaign->fresh(), $actor);
        $this->assertSame(1, $metrics['runs']['started']);
        $this->assertSame(1, $metrics['runs']['blocked']);
        $this->assertSame(1, $metrics['blocks']['budget']);
        $this->assertSame(0, $metrics['usage']['emails_sent']);
        $this->assertSame(0, $metrics['usage']['timeweb_requests']);
    }
}
