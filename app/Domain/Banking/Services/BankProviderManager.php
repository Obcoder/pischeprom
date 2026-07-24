<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Exceptions\BankConfigurationException;

class BankProviderManager
{
    /** @var array<string, BankProviderInterface> */
    private array $resolved = [];

    public function driver(?string $provider = null): BankProviderInterface
    {
        $provider ??= (string) config('banking.provider', 'sber');

        if (isset($this->resolved[$provider])) {
            return $this->resolved[$provider];
        }

        $class = config("banking.providers.{$provider}");

        if (! is_string($class) || ! is_a($class, BankProviderInterface::class, true)) {
            throw new BankConfigurationException("Bank provider [{$provider}] is not configured.");
        }

        return $this->resolved[$provider] = app($class);
    }
}
