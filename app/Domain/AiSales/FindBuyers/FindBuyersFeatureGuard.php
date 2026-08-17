<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Services\ProspectingFeatureGuard;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindBuyersFeatureGuard
{
    public function __construct(private readonly ProspectingFeatureGuard $prospecting) {}

    public function ui(): void
    {
        $this->prospecting->jobs();
        $this->enabled('ui_enabled');
        $this->assertStage11ExecutionIsOff();
    }

    public function drafts(): void
    {
        $this->ui();
        $this->enabled('drafts_enabled');
    }

    public function planning(): void
    {
        $this->drafts();
        $this->prospecting->queryPlanning();
    }

    public function assertStage11ExecutionIsOff(): void
    {
        $unsafe = [
            'find_buyers.live_execution_enabled',
            'find_buyers.auto_research_enabled',
            'find_buyers.auto_scoring_enabled',
            'prospecting.search_execution_enabled',
            'prospecting.page_fetch_enabled',
            'prospecting.public_research_enabled',
            'prospecting.auto_candidate_ingestion_enabled',
            'prospecting.auto_scoring_enabled',
        ];

        foreach ($unsafe as $key) {
            if ((bool) config("ai-sales.{$key}", false)) {
                throw new LogicException("Stage 11 is code-only; {$key} must remain disabled.");
            }
        }

        if ((bool) config('ai-sales.external_calls_enabled', false)
            || (bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new LogicException('Stage 11 external calls and provider failover must remain disabled.');
        }
    }

    /** @return array<string, bool|int|string> */
    public function runtimeState(): array
    {
        return [
            'stage' => 11,
            'ui_enabled' => (bool) config('ai-sales.find_buyers.ui_enabled', false),
            'drafts_enabled' => (bool) config('ai-sales.find_buyers.drafts_enabled', false),
            'query_planning_enabled' => (bool) config('ai-sales.prospecting.query_planning_enabled', false),
            'live_execution_allowed' => false,
            'search_execution_enabled' => (bool) config('ai-sales.prospecting.search_execution_enabled', false),
            'page_fetch_enabled' => (bool) config('ai-sales.prospecting.page_fetch_enabled', false),
            'timeweb_research_enabled' => false,
            'auto_candidate_ingestion_enabled' => (bool) config('ai-sales.prospecting.auto_candidate_ingestion_enabled', false),
            'auto_scoring_enabled' => (bool) config('ai-sales.find_buyers.auto_scoring_enabled', false),
            'retries' => 0,
            'failovers' => 0,
            'kill_switches' => 'blocking',
        ];
    }

    private function enabled(string $key): void
    {
        if (! (bool) config("ai-sales.find_buyers.{$key}", false)) {
            throw new NotFoundHttpException('Find Buyers is disabled.');
        }
    }
}
