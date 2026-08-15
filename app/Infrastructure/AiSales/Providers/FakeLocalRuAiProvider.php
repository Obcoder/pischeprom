<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Enums\AiProviderRoute;

class FakeLocalRuAiProvider extends AbstractFakeAiProvider
{
    public function route(): AiProviderRoute
    {
        return AiProviderRoute::LocalRu;
    }

    protected function modelId(): string
    {
        return 'fake-local-ru-v1';
    }
}
