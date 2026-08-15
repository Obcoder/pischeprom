<?php

namespace App\Domain\AiSales\Providers;

use App\Domain\AiSales\Contracts\AiProviderInterface;
use App\Domain\AiSales\Contracts\FakeAiProviderInterface;
use App\Domain\AiSales\Enums\AiProviderRoute;
use LogicException;

class AiProviderRegistry
{
    /** @var array<string, FakeAiProviderInterface> */
    private array $providers = [];

    public function register(AiProviderInterface $provider): void
    {
        if (! $provider instanceof FakeAiProviderInterface) {
            throw new LogicException('Stage 04 accepts fake AI providers only.');
        }

        $key = $this->key($provider->code(), $provider->route());

        if (isset($this->providers[$key])) {
            throw new LogicException("AI provider route {$key} is already registered.");
        }

        $this->providers[$key] = $provider;
    }

    public function forRoute(AiProviderRoute $route): FakeAiProviderInterface
    {
        $matches = array_values(array_filter(
            $this->providers,
            fn (FakeAiProviderInterface $provider): bool => $provider->route() === $route,
        ));

        if (count($matches) !== 1) {
            throw new LogicException('Stage 04 requires exactly one fake provider per processing route.');
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
