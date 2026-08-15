<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;

class TimewebUsageNormalizer
{
    public function chat(array $usage, int $toolCalls = 0): AiProviderUsage
    {
        return new AiProviderUsage(
            $this->unsigned($usage['prompt_tokens'] ?? null),
            $this->unsigned($usage['completion_tokens'] ?? null),
            $this->unsigned(data_get($usage, 'completion_tokens_details.reasoning_tokens')),
            $this->unsigned(data_get($usage, 'prompt_tokens_details.cached_tokens')),
            0,
            $toolCalls,
            null,
            null,
            '0.0000',
        );
    }

    public function responses(array $usage, int $toolCalls = 0): AiProviderUsage
    {
        return new AiProviderUsage(
            $this->unsigned($usage['input_tokens'] ?? null),
            $this->unsigned($usage['output_tokens'] ?? null),
            $this->unsigned(data_get($usage, 'output_tokens_details.reasoning_tokens')),
            $this->unsigned(data_get($usage, 'input_tokens_details.cached_tokens')),
            0,
            $toolCalls,
            null,
            null,
            '0.0000',
        );
    }

    private function unsigned(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized >= 0 && $normalized <= 100_000_000 ? $normalized : null;
    }
}
