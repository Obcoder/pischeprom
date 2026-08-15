<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Enums\AiProviderRoute;

class FakeExternalSanitizedAiProvider extends AbstractFakeAiProvider
{
    public function route(): AiProviderRoute
    {
        return AiProviderRoute::ExternalSanitized;
    }

    protected function modelId(): string
    {
        return 'fake-external-sanitized-v1';
    }

    protected function capabilityCodes(): array
    {
        return [
            ...parent::capabilityCodes(),
            'reasoning',
            'store_false',
        ];
    }
}
