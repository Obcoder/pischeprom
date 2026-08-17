<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;

class OutreachFeatureGuard
{
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

    public function suppressionManagement(): void
    {
        $this->assertFlag('suppression_management_enabled');
    }

    public function dispatchAllowed(): bool
    {
        // Stage 12 deliberately has no dispatch path, even under misconfigured flags.
        return false;
    }

    public function state(): array
    {
        return [
            'ui' => (bool) config('ai-sales.outreach.ui_enabled', false),
            'drafts' => (bool) config('ai-sales.outreach.drafts_enabled', false),
            'fake_generation' => (bool) config('ai-sales.outreach.fake_generation_enabled', false),
            'live_generation' => false,
            'dispatch' => false,
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
