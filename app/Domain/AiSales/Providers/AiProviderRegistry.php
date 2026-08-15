<?php

namespace App\Domain\AiSales\Providers;

use App\Domain\AiSales\Contracts\AiProviderInterface;
use App\Domain\AiSales\Contracts\FakeAiProviderInterface;
use App\Domain\AiSales\Contracts\TimewebAiProviderInterface;
use App\Domain\AiSales\Enums\AiProviderRoute;
use LogicException;

class AiProviderRegistry
{
    /** @var array<string, AiProviderInterface> */
    private array $providers = [];

    public function register(AiProviderInterface $provider): void
    {
        if (! $provider instanceof FakeAiProviderInterface && ! $provider instanceof TimewebAiProviderInterface) {
            throw new LogicException('Only approved fake or Timeweb AI provider contracts may be registered.');
        }

        if ($provider instanceof TimewebAiProviderInterface
            && config('ai-sales.transport_mode') !== 'timeweb_synthetic_only') {
            throw new LogicException('Timeweb providers require the explicit synthetic-only transport mode.');
        }

        $key = $this->key($provider->code(), $provider->route());

        if (isset($this->providers[$key])) {
            throw new LogicException("AI provider route {$key} is already registered.");
        }

        $this->providers[$key] = $provider;
    }

    public function forRoute(AiProviderRoute $route): AiProviderInterface
    {
        $matches = array_values(array_filter(
            $this->providers,
            fn (AiProviderInterface $provider): bool => $provider->route() === $route,
        ));

        if (count($matches) !== 1) {
            throw new LogicException('Exactly one approved provider must be registered per processing route.');
        }

        return $matches[0];
    }

    public function all(): array
    {
        return array_values($this->providers);
    }

    private function key(string $code, AiProviderRoute $route): string
    {
        return $code.':'.$route->value;
    }
}
