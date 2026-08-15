<?php

namespace App\Domain\AiSales\DTO\Providers;

use InvalidArgumentException;

final readonly class AiRequestRequirements
{
    public array $capabilities;

    public function __construct(
        array $capabilities,
        public int $maxInputTokens = 4_000,
        public int $maxOutputTokens = 1_000,
        public bool $requiresStoreFalse = true,
    ) {
        $normalized = array_values(array_unique(array_map(static function (mixed $capability): string {
            if (! is_string($capability) || ! preg_match('/^[a-z0-9_]+$/', $capability)) {
                throw new InvalidArgumentException('AI capability codes must be explicit snake_case strings.');
            }

            return $capability;
        }, array_slice($capabilities, 0, 32))));

        if ($maxInputTokens < 1 || $maxOutputTokens < 1) {
            throw new InvalidArgumentException('AI token requirements must be positive.');
        }

        $this->capabilities = $normalized;
    }
}
