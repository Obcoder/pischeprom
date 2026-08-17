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
            throw new \LogicException('The deprecated unprofiled prospecting live-search flag is blocked.');
        }
    }

    public function queryPlanning(): void
    {
        $this->jobs();
        $this->enabled('prospecting.query_planning_enabled');
    }

    public function searchExecution(): void
    {
        $this->queryPlanning();
        $this->assertNoLiveSearch();
        $this->enabled('prospecting.search_execution_enabled');
        $this->enabled('prospecting.existing_yandex_provider_enabled');

        if (! (bool) config('ai-sales.web_search_enabled', false)) {
            throw new NotFoundHttpException('AI prospecting web search is disabled.');
        }
        if ((bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new \LogicException('Search provider failover is forbidden.');
        }
        if ((bool) config('ai-sales.prospecting.auto_candidate_ingestion_enabled', false)) {
            throw new \LogicException('Automatic Candidate ingestion is forbidden in Stage 09.');
        }
    }

    public function pageFetch(): void
    {
        $this->searchExecution();
        $this->enabled('prospecting.page_fetch_enabled');
    }

    public function publicResearch(): void
    {
        $this->pageFetch();
        $this->enabled('prospecting.public_research_enabled');

        if (config('ai-sales.transport_mode') !== 'fake_only'
            || (bool) config('ai-sales.external_calls_enabled', false)
            || (bool) config('ai-sales.provider_native_tools_enabled', false)) {
            throw new \LogicException('Stage 09 public research requires fake-only AI with native tools disabled.');
        }
    }

    private function enabled(string $key): void
    {
        if (! (bool) config("ai-sales.{$key}", false)) {
            throw new NotFoundHttpException('AI prospecting feature is disabled.');
        }
    }
}
