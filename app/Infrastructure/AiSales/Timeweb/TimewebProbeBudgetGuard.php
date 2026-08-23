<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Exceptions\PolicyViolation;

class TimewebProbeBudgetGuard
{
    private int $requests = 0;

    private int $inputTokens = 0;

    private int $outputTokens = 0;

    private int $rubUnits = 0;

    private readonly float $startedAt;

    public function __construct(private readonly TimewebAiGatewayConfiguration $configuration)
    {
        $this->startedAt = microtime(true);
    }

    public function reserve(int $maxInputTokens, int $maxOutputTokens, string $maxRub): void
    {
        $limits = $this->configuration->probeLimits();
        $rubUnits = $this->rubUnits($maxRub);

        if ($this->requests + 1 > $limits['max_requests']
            || $this->inputTokens + $maxInputTokens > $limits['max_input_tokens']
            || $this->outputTokens + $maxOutputTokens > $limits['max_output_tokens']
            || $this->rubUnits + $rubUnits > $this->rubUnits($limits['max_rub'])
            || microtime(true) - $this->startedAt > $limits['max_wall_clock_seconds']) {
            throw new PolicyViolation('timeweb_probe_budget_exceeded', 'The next synthetic probe request exceeds a hard Stage 05 budget.');
        }

        $this->requests++;
        $this->inputTokens += $maxInputTokens;
        $this->outputTokens += $maxOutputTokens;
        $this->rubUnits += $rubUnits;
    }

    public function reconcile(AiProviderUsage $usage): void
    {
        $limits = $this->configuration->probeLimits();

        if ($usage->inputTokens !== null && $usage->inputTokens > $limits['max_input_tokens']) {
            throw new PolicyViolation('timeweb_probe_usage_exceeded', 'Provider input usage exceeded the hard synthetic probe cap.');
        }

        if ($usage->outputTokens !== null && $usage->outputTokens > $limits['max_output_tokens']) {
            throw new PolicyViolation('timeweb_probe_usage_exceeded', 'Provider output usage exceeded the hard synthetic probe cap.');
        }

        if ($usage->reasoningTokens !== null
            && ($usage->reasoningTokens > $limits['max_output_tokens']
                || ($usage->outputTokens !== null && $usage->reasoningTokens > $usage->outputTokens))) {
            throw new PolicyViolation('timeweb_probe_usage_exceeded', 'Provider reasoning usage is inconsistent with the hard synthetic probe cap.');
        }
    }

    public function summary(): array
    {
        return [
            'request_count' => $this->requests,
            'reserved_input_tokens' => $this->inputTokens,
            'reserved_output_tokens' => $this->outputTokens,
            'reserved_rub' => number_format($this->rubUnits / 10_000, 4, '.', ''),
            'elapsed_ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
        ];
    }

    public function remainingTimeoutSeconds(): float
    {
        $remaining = $this->configuration->probeLimits()['max_wall_clock_seconds']
            - (microtime(true) - $this->startedAt);

        if ($remaining <= 0) {
            throw new PolicyViolation('timeweb_probe_budget_exceeded', 'The synthetic probe wall-clock cap is exhausted.');
        }

        return min((float) $this->configuration->timeout(), $remaining);
    }

    private function rubUnits(mixed $amount): int
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            throw new PolicyViolation('timeweb_probe_rub_cap_invalid', 'Probe RUB amounts must use a non-negative decimal value.');
        }

        $value = trim((string) $amount);

        if (preg_match('/^\d{1,10}(?:\.\d{1,4})?$/', $value) !== 1) {
            throw new PolicyViolation('timeweb_probe_rub_cap_invalid', 'Probe RUB amounts must use at most four decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 10_000) + (int) str_pad($fraction, 4, '0');
    }
}
