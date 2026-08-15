<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Exceptions\PolicyViolation;

class AiStage04FeatureGuard
{
    public function __construct(private readonly AiKillSwitchService $killSwitches) {}

    public function assertEnabled(AiProcessingContour $contour): void
    {
        if (config('ai-sales.transport_mode') !== 'fake_only') {
            throw new PolicyViolation('stage04_fake_only', 'Stage 04 permits fake providers only.');
        }

        if ((bool) config('ai-sales.external_calls_enabled', false)) {
            throw new PolicyViolation('external_egress_forbidden_stage04', 'External HTTP egress must remain disabled in Stage 04.');
        }

        if ((bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new PolicyViolation('failover_forbidden_stage04', 'Provider failover must remain disabled in Stage 04.');
        }

        if (! (bool) config('ai-sales.enabled', false) || ! (bool) config('ai-sales.fake_execution_enabled', false)) {
            throw new PolicyViolation('ai_sales_disabled', 'AI Sales fake execution is disabled.');
        }

        $contourEnabled = match ($contour) {
            AiProcessingContour::LocalRu => (bool) config('ai-sales.local_ru_calls_enabled', false),
            AiProcessingContour::ExternalSanitized => (bool) config('ai-sales.external_sanitized_calls_enabled', false),
            AiProcessingContour::None => false,
        };

        if (! $contourEnabled) {
            throw new PolicyViolation('ai_contour_disabled', 'The requested AI processing contour is disabled.');
        }

        $this->killSwitches->assertOpen($contour);
    }
}
