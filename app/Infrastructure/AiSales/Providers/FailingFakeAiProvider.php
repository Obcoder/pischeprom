<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Enums\FakeAiProviderScenario;

class FailingFakeAiProvider extends AbstractFakeAiProvider
{
    public function __construct(
        private readonly AiProviderRoute $providerRoute,
        FakeAiProviderScenario $scenario = FakeAiProviderScenario::ProviderUnavailable,
    ) {
        parent::__construct($scenario);
    }

    public function route(): AiProviderRoute
    {
        return $this->providerRoute;
    }

    protected function modelId(): string
    {
        return $this->providerRoute === AiProviderRoute::LocalRu
            ? 'fake-local-ru-v1'
            : 'fake-external-sanitized-v1';
    }
}
