<?php

namespace App\Domain\AiSales\Services;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProspectingFeatureGuard
{
    public function dossier(): void
    {
        $this->enabled('prospecting.dossier_enabled');
    }

    public function jobs(): void
    {
        $this->dossier();
        $this->enabled('prospecting.jobs_enabled');
    }

    public function candidateImport(): void
    {
        $this->jobs();
        $this->enabled('prospecting.candidate_import_enabled');
    }

    public function assertNoLiveSearch(): void
    {
        if ((bool) config('ai-sales.prospecting.live_search_enabled', false)) {
            throw new \LogicException('Live prospecting search belongs to Stage 09 and is blocked in Stage 08.');
        }
    }

    private function enabled(string $key): void
    {
        if (! (bool) config("ai-sales.{$key}", false)) {
            throw new NotFoundHttpException('AI prospecting feature is disabled.');
        }
    }
}
