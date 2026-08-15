<?php

namespace App\Infrastructure\AiSales\Providers;

use App\Domain\AiSales\Enums\AiProviderRoute;

class TimewebExternalSanitizedProvider extends AbstractTimewebAiProvider
{
    public function route(): AiProviderRoute
    {
        return AiProviderRoute::ExternalSanitized;
    }
}
