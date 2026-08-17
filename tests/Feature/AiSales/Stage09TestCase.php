<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Search\SearchProviderRegistry;
use App\Domain\AiSales\Services\ApproveProspectingQueryPlan;
use App\Domain\AiSales\Services\PlanProspectingQueries;
use App\Domain\AiSales\Web\PublicDnsResolver;
use App\Infrastructure\AiSales\Search\FakeSearchProvider;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
use App\Models\User;

abstract class Stage09TestCase extends Stage08TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => true,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => true,
            'ai-sales.prospecting.page_fetch_enabled' => true,
            'ai-sales.prospecting.public_research_enabled' => true,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.live_search_enabled' => false,
            'ai-sales.web_search_enabled' => true,
            'ai-sales.transport_mode' => 'fake_only',
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_native_tools_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.queue.connection' => 'sync',
        ]);
        app()->forgetInstance(SearchProviderRegistry::class);
        app()->instance(SearchProviderRegistry::class, new SearchProviderRegistry([new FakeSearchProvider]));
        app()->forgetInstance(PublicDnsResolver::class);
        app()->instance(PublicDnsResolver::class, new PublicDnsResolver([
            'buyer.synthetic.example' => ['93.184.216.34'],
            'catalog.synthetic.example' => ['93.184.216.34'],
            'company.example' => ['93.184.216.34'],
            'directory.example' => ['93.184.216.34'],
        ]));
    }

    protected function approvedPlannedJob(
        User $actor,
        string $purpose = 'buyer_discovery',
        ?Good $good = null,
        ?Product $product = null,
    ): ProspectingSearchJob {
        $job = $this->approvedJob($actor, $purpose, $good, $product);
        app(PlanProspectingQueries::class)->handle($job, $actor);
        app(ApproveProspectingQueryPlan::class)->handle($job, $actor);

        return $job->fresh(['products', 'goods', 'queries']);
    }
}
