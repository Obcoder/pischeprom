<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClientAcquisitionCampaignFeatureGuard
{
    public function campaigns(): void
    {
        $this->enabled('enabled');
        $this->enabled('autonomous_campaigns_enabled');
        $this->enabled('campaigns.enabled');
        $this->assertSafetyInvariants();
    }

    public function scheduler(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.scheduler_enabled');
        if (app()->environment('production')) {
            throw new PolicyViolation('campaign_scheduler_production_blocked', 'Stage 14 production scheduler execution is blocked.');
        }
    }

    public function liveSearch(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.live_search_enabled');
        $this->enabled('web_search_enabled');
        $this->enabled('prospecting.search_execution_enabled');
        $this->enabled('prospecting.existing_yandex_provider_enabled');
    }

    public function liveResearch(): void
    {
        $this->liveSearch();
        $this->enabled('campaigns.live_research_enabled');
        $this->enabled('prospecting.page_fetch_enabled');
        $this->enabled('prospecting.public_research_enabled');
    }

    public function autoIngest(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.auto_ingest_enabled');
    }

    public function autoUnit(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.auto_create_unit_enabled');
        $this->enabled('prospecting.auto_create_unit');
    }

    public function autoScoring(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.auto_scoring_enabled');
        $this->enabled('prospecting.scoring_enabled');
    }

    public function autoDraft(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.auto_draft_enabled');
        $this->enabled('outreach.drafts_enabled');
        $this->enabled('outreach.fake_generation_enabled');
    }

    public function notifications(): void
    {
        $this->campaigns();
        $this->enabled('campaigns.notifications_enabled');
    }

    public function assertSafetyInvariants(): void
    {
        if ((int) config('ai-sales.limits.max_retries', 0) !== 0
            || (bool) config('ai-sales.provider_failover_enabled', false)
            || (bool) config('ai-sales.provider_native_tools_enabled', false)
            || (bool) config('ai-sales.outreach.dispatch_enabled', false)
            || (bool) config('ai-sales.outreach.provider_send_enabled', false)
            || (bool) config('ai-sales.outreach.auto_followup_enabled', false)
            || (bool) config('ai-sales.outreach.auto_send_enabled', false)) {
            throw new PolicyViolation('campaign_safety_invariant_blocked', 'Campaign safety invariants are not in their required blocking state.');
        }
    }

    private function enabled(string $key): void
    {
        if (! (bool) config('ai-sales.'.$key, false)) {
            throw new NotFoundHttpException('AI Sales campaigns are disabled.');
        }
    }
}
