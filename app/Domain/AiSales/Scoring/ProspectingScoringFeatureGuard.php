<?php

namespace App\Domain\AiSales\Scoring;

use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProspectingScoringFeatureGuard
{
    public function scoring(): void
    {
        if (! (bool) config('ai-sales.prospecting.scoring_enabled', false)) {
            throw new NotFoundHttpException('AI prospecting scoring is disabled.');
        }
        if ((bool) config('ai-sales.prospecting.live_scoring_enabled', false)) {
            throw new LogicException('Live scoring is forbidden in Stage 10.');
        }
    }

    public function override(): void
    {
        $this->scoring();
        if (! (bool) config('ai-sales.prospecting.score_overrides_enabled', false)) {
            throw new NotFoundHttpException('AI prospecting score overrides are disabled.');
        }
    }

    public function aiEvidence(): void
    {
        $this->scoring();
        if (! (bool) config('ai-sales.prospecting.ai_evidence_enabled', false)) {
            throw new NotFoundHttpException('AI prospecting score evidence is disabled.');
        }
        if (config('ai-sales.transport_mode') !== 'fake_only'
            || (bool) config('ai-sales.external_calls_enabled', false)
            || (bool) config('ai-sales.provider_native_tools_enabled', false)) {
            throw new LogicException('Stage 10 evidence workflow is fake-only and tool-free.');
        }
    }
}
