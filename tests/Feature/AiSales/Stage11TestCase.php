<?php

namespace Tests\Feature\AiSales;

abstract class Stage11TestCase extends Stage10TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'ai-sales.find_buyers.ui_enabled' => true,
            'ai-sales.find_buyers.drafts_enabled' => true,
            'ai-sales.find_buyers.live_execution_enabled' => false,
            'ai-sales.find_buyers.auto_research_enabled' => false,
            'ai-sales.find_buyers.auto_scoring_enabled' => false,
            'ai-sales.prospecting.query_planning_enabled' => true,
            'ai-sales.prospecting.search_execution_enabled' => false,
            'ai-sales.prospecting.existing_yandex_provider_enabled' => false,
            'ai-sales.prospecting.page_fetch_enabled' => false,
            'ai-sales.prospecting.public_research_enabled' => false,
            'ai-sales.prospecting.auto_candidate_ingestion_enabled' => false,
            'ai-sales.prospecting.auto_scoring_enabled' => false,
            'ai-sales.web_search_enabled' => false,
            'ai-sales.external_calls_enabled' => false,
            'ai-sales.provider_failover_enabled' => false,
            'ai-sales.transport_mode' => 'fake_only',
        ]);
    }
}
