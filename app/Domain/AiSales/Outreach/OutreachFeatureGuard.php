<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiKillSwitchService;

class OutreachFeatureGuard
{
    public function __construct(private readonly AiKillSwitchService $killSwitches) {}

    public function view(): void
    {
        $this->assertFlag('ui_enabled');
    }

    public function drafts(): void
    {
        $this->assertFlag('drafts_enabled');

        if (! config('ai-sales.outreach_drafting_enabled', false)) {
            throw new PolicyViolation('outreach_global_drafting_disabled', 'Outreach drafting is disabled.');
        }
    }

    public function fakeGeneration(): void
    {
        $this->drafts();
        $this->assertFlag('fake_generation_enabled');

        if (config('ai-sales.outreach.transport_mode', 'fake_only') !== 'fake_only'
            || config('ai-sales.outreach.live_generation_enabled', false)) {
            throw new PolicyViolation('outreach_fake_only_required', 'Only deterministic fake outreach generation is allowed.');
        }
    }

    public function permissionLedger(): void
    {
        $this->assertFlag('permission_ledger_enabled');
    }

    public function liveSyntheticCanary(): void
    {
        $this->drafts();

        if (! app()->environment('testing')
            || ! config('ai-sales.outreach.live_synthetic_canary_enabled', false)
            || ! config('ai-sales.outreach.live_generation_enabled', false)
            || config('ai-sales.outreach.transport_mode') !== 'timeweb_synthetic_only'
            || config('ai-sales.transport_mode') !== 'timeweb_synthetic_only'
            || ! config('ai-sales.external_calls_enabled', false)
            || ! config('ai-sales.external_sanitized_calls_enabled', false)
            || config('ai-sales.provider_failover_enabled', false)
            || config('ai-sales.provider_native_tools_enabled', false)
            || config('ai-sales.outreach_sending_enabled', false)
            || config('ai-sales.outreach.dispatch_enabled', false)
            || config('ai-sales.outreach.auto_send_enabled', false)) {
            throw new PolicyViolation('outreach_live_canary_blocked', 'The bounded testing-only outreach canary is not explicitly enabled.');
        }
    }

    public function suppressionManagement(): void
    {
        $this->assertFlag('suppression_management_enabled');
    }

    public function dispatchAllowed(): bool
    {
        return (bool) config('ai-sales.enabled', false)
            && (bool) config('ai-sales.outreach.dispatch_pipeline_enabled', false)
            && (bool) config('ai-sales.outreach.dispatch_enabled', false)
            && (bool) config('ai-sales.outreach_sending_enabled', false);
    }

    public function dispatchPipeline(): void
    {
        $this->drafts();
        $this->assertFlag('dispatch_pipeline_enabled');
    }

    public function queue(): void
    {
        $this->dispatchPipeline();
        $this->assertFlag('queue_enabled');

        if (! $this->dispatchAllowed()) {
            throw new PolicyViolation('outreach_dispatch_disabled', 'Outreach dispatch is disabled.');
        }
    }

    public function providerSend(): void
    {
        $this->queue();
        $this->assertFlag('provider_send_enabled');

        if ((int) config('ai-sales.outreach.limits.provider_retries', 0) !== 0
            || (bool) config('ai-sales.outreach.limits.provider_failover', false)
            || (bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new PolicyViolation('outreach_zero_retry_required', 'Outreach provider retry and failover must remain disabled.');
        }

        $this->killSwitches->assertGlobalOpen();
    }

    public function eventIngestionEnabled(): bool
    {
        return (bool) config('ai-sales.enabled', false)
            && (bool) config('ai-sales.outreach.event_ingestion_enabled', false);
    }

    public function replyCorrelationEnabled(): bool
    {
        return (bool) config('ai-sales.enabled', false)
            && (bool) config('ai-sales.outreach.reply_correlation_enabled', false);
    }

    public function replyTriage(): void
    {
        $this->assertFlag('reply_triage_enabled');

        if (config('ai-sales.outreach.transport_mode', 'fake_only') !== 'fake_only') {
            throw new PolicyViolation('outreach_reply_triage_fake_only', 'Reply triage is fake-only in Stage 13.');
        }
    }

    public function followupPlanning(): void
    {
        $this->assertFlag('followup_planning_enabled');

        if ((bool) config('ai-sales.outreach.auto_followup_enabled', false)
            || (int) config('ai-sales.outreach.limits.max_follow_ups', 0) !== 0) {
            throw new PolicyViolation('outreach_auto_followup_disabled', 'Automatic follow-up is disabled in Stage 13.');
        }
    }

    public function state(): array
    {
        return [
            'ui' => (bool) config('ai-sales.outreach.ui_enabled', false),
            'drafts' => (bool) config('ai-sales.outreach.drafts_enabled', false),
            'fake_generation' => (bool) config('ai-sales.outreach.fake_generation_enabled', false),
            'live_generation' => false,
            'dispatch' => $this->dispatchAllowed(),
            'dispatch_pipeline' => (bool) config('ai-sales.outreach.dispatch_pipeline_enabled', false),
            'queue' => (bool) config('ai-sales.outreach.queue_enabled', false),
            'provider_send' => (bool) config('ai-sales.outreach.provider_send_enabled', false),
            'event_ingestion' => (bool) config('ai-sales.outreach.event_ingestion_enabled', false),
            'reply_correlation' => (bool) config('ai-sales.outreach.reply_correlation_enabled', false),
            'reply_triage' => (bool) config('ai-sales.outreach.reply_triage_enabled', false),
            'followup_planning' => (bool) config('ai-sales.outreach.followup_planning_enabled', false),
            'auto_followup' => false,
            'auto_send' => false,
            'transport_mode' => 'fake_only',
        ];
    }

    private function assertFlag(string $flag): void
    {
        if (! config('ai-sales.enabled', false) || ! config('ai-sales.outreach.'.$flag, false)) {
            throw new PolicyViolation('outreach_feature_disabled', 'Outreach feature is disabled.');
        }
    }
}
