<?php

namespace Tests\Feature\AiSales;

use Tests\TestCase;

class Stage11DefaultOffAndCliTest extends TestCase
{
    public function test_stage11_flags_are_default_off_and_routes_keep_security_middleware(): void
    {
        foreach (['ui_enabled', 'drafts_enabled', 'live_execution_enabled', 'auto_research_enabled', 'auto_scoring_enabled'] as $flag) {
            $this->assertFalse((bool) config('ai-sales.find_buyers.'.$flag));
        }
        $this->assertFalse((bool) config('ai-sales.prospecting.search_execution_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.page_fetch_enabled'));
        $this->assertFalse((bool) config('ai-sales.prospecting.public_research_enabled'));
        $this->assertFalse((bool) config('ai-sales.external_calls_enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));

        foreach ([
            'api.ai-sales.find-buyers.launch-context', 'api.ai-sales.find-buyers.geography',
            'api.ai-sales.find-buyers.dashboard', 'api.ai-sales.find-buyers.drafts.store',
            'api.ai-sales.find-buyers.drafts.update', 'api.ai-sales.find-buyers.drafts.plan',
            'api.ai-sales.find-buyers.drafts.submit', 'api.ai-sales.find-buyers.jobs.cancel',
            'api.ai-sales.find-buyers.jobs.progress',
        ] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('verified', $middleware);
            $this->assertContains('throttle:ai-sales', $middleware);
        }
        $campaignRun = app('router')->getRoutes()->getByName('api.ai-sales.campaigns.run');
        $this->assertNotNull($campaignRun);
        $campaignMiddleware = $campaignRun->gatherMiddleware();
        foreach (['auth:sanctum', 'verified', 'throttle:ai-sales', 'throttle:ai-sales-campaigns'] as $middleware) {
            $this->assertContains($middleware, $campaignMiddleware);
        }
        $routes = file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('find-buyers/jobs/{prospectingSearchJob}/execute', $routes);
        $this->assertStringNotContainsString('find-buyers/jobs/{prospectingSearchJob}/live', $routes);
    }

    public function test_existing_product_yandex_card_is_not_duplicated_by_find_buyers_ui(): void
    {
        $productPage = file_get_contents(resource_path('js/Pages/Ameise/Product_02.vue'));
        $goodPage = file_get_contents(resource_path('js/Pages/Ameise/Good.vue'));
        $yandexCard = file_get_contents(resource_path('js/Components/ProductYandexSearchCard.vue'));
        $wizard = file_get_contents(resource_path('js/Components/AiSales/FindBuyersWizard.vue'));

        $this->assertSame(1, substr_count($productPage, '<ProductYandexSearchCard'));
        $this->assertSame(1, substr_count($productPage, '<FindBuyersLauncher'));
        $this->assertSame(1, substr_count($goodPage, '<FindBuyersLauncher'));
        $this->assertStringContainsString('/api/products/${props.productId}/yandex-search', $yandexCard);
        $this->assertStringNotContainsString('FindBuyers', $yandexCard);
        $this->assertStringContainsString('Live execution выключен', $wizard);
        $this->assertStringNotContainsString('Выполнить live', $wizard);
    }
}
