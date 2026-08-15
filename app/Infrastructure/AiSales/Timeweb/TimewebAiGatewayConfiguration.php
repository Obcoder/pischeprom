<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiKillSwitchService;
use Throwable;

class TimewebAiGatewayConfiguration
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing', 'staging'];

    public function __construct(private readonly AiKillSwitchService $killSwitches) {}

    public function assertProbeReady(AiProviderRoute $route): void
    {
        if (! in_array(app()->environment(), self::ALLOWED_ENVIRONMENTS, true)) {
            throw new PolicyViolation('timeweb_environment_blocked', 'Timeweb probes are allowed only in local, testing or staging environments.');
        }

        $contourEnabled = match ($route) {
            AiProviderRoute::LocalRu => (bool) config('ai-sales.local_ru_calls_enabled', false),
            AiProviderRoute::ExternalSanitized => (bool) config('ai-sales.external_sanitized_calls_enabled', false),
        };

        if (config('ai-sales.transport_mode') !== 'timeweb_synthetic_only'
            || ! (bool) config('ai-sales.enabled', false)
            || ! (bool) config('ai-sales.external_calls_enabled', false)
            || ! $contourEnabled
            || ! (bool) config('ai-sales.providers.timeweb.enabled', false)
            || ! (bool) config("ai-sales.providers.timeweb.routes.{$route->value}.enabled", false)) {
            throw new PolicyViolation('timeweb_route_disabled', 'AI Sales, synthetic transport, egress, Timeweb and the exact probe route must be explicitly enabled.');
        }

        if ((bool) config('ai-sales.provider_failover_enabled', false)) {
            throw new PolicyViolation('timeweb_failover_must_be_disabled', 'Timeweb Stage 05 probes require provider failover to remain disabled.');
        }

        if (! (bool) config('ai-sales.providers.timeweb.probe.enabled', false)
            || ! (bool) config('ai-sales.providers.timeweb.probe.synthetic_only', false)) {
            throw new PolicyViolation('timeweb_probe_disabled', 'Timeweb probe and synthetic-only guards must both be enabled.');
        }

        $this->assertBaseUrl();
        $this->assertTransportBounds();
        $this->assertDistinctRouteKeys();
        $this->assertBudgetConfigured();

        try {
            $this->killSwitches->assertOpen($route->contour());
        } catch (PolicyViolation $violation) {
            throw $violation;
        } catch (Throwable) {
            throw new PolicyViolation('timeweb_kill_switch_unavailable', 'Timeweb probes require readable fail-closed kill-switch state.');
        }
    }

    public function assertBaseUrl(): string
    {
        $baseUrl = (string) config('ai-sales.providers.timeweb.base_url', '');
        $parts = parse_url($baseUrl);

        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || mb_strtolower((string) ($parts['host'] ?? '')) !== 'api.timeweb.ai'
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || rtrim((string) ($parts['path'] ?? ''), '/') !== '/v1'
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new PolicyViolation('timeweb_base_url_blocked', 'Timeweb Base URL must be exactly the approved HTTPS host and /v1 path.');
        }

        return 'https://api.timeweb.ai/v1';
    }

    public function apiKey(AiProviderRoute $route): string
    {
        $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

        if (! $this->validApiKey($key)) {
            throw new PolicyViolation('timeweb_key_missing', 'The selected Timeweb route has no configured staging key.');
        }

        return $key;
    }

    public function fingerprint(AiProviderRoute $route): ?string
    {
        $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

        if (! $this->validApiKey($key)) {
            return null;
        }

        $hmacKey = config('app.key');

        if (! is_string($hmacKey) || $hmacKey === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $key, $hmacKey), -12);
    }

    public function connectTimeout(): int
    {
        return (int) config('ai-sales.providers.timeweb.connect_timeout_seconds', 5);
    }

    public function timeout(): int
    {
        return (int) config('ai-sales.providers.timeweb.timeout_seconds', 45);
    }

    public function maxResponseBytes(): int
    {
        return (int) config('ai-sales.providers.timeweb.max_response_bytes', 1_048_576);
    }

    public function probeLimits(): array
    {
        return [
            'max_rub' => (string) config('ai-sales.providers.timeweb.probe.max_rub', ''),
            'max_input_tokens' => (int) config('ai-sales.providers.timeweb.probe.max_input_tokens', 0),
            'max_output_tokens' => (int) config('ai-sales.providers.timeweb.probe.max_output_tokens', 0),
            'max_requests' => (int) config('ai-sales.providers.timeweb.probe.max_requests', 0),
            'max_wall_clock_seconds' => (int) config('ai-sales.providers.timeweb.probe.max_wall_clock_seconds', 0),
        ];
    }

    private function assertDistinctRouteKeys(): void
    {
        $local = $this->apiKey(AiProviderRoute::LocalRu);
        $external = $this->apiKey(AiProviderRoute::ExternalSanitized);

        if (hash_equals(hash('sha256', $local), hash('sha256', $external))) {
            throw new PolicyViolation('timeweb_route_keys_not_separated', 'Timeweb local and external routes require different keys.');
        }
    }

    private function assertTransportBounds(): void
    {
        if ($this->connectTimeout() < 1 || $this->connectTimeout() > 10
            || $this->timeout() < 1 || $this->timeout() > 60
            || $this->maxResponseBytes() < 1024 || $this->maxResponseBytes() > 1_048_576) {
            throw new PolicyViolation('timeweb_transport_bounds_invalid', 'Timeweb transport bounds are outside the Stage 05 limits.');
        }
    }

    private function assertBudgetConfigured(): void
    {
        $limits = $this->probeLimits();

        if (preg_match('/^\d{1,3}(?:\.\d{1,4})?$/', $limits['max_rub']) !== 1
            || (float) $limits['max_rub'] <= 0 || (float) $limits['max_rub'] > 100
            || $limits['max_input_tokens'] < 1 || $limits['max_input_tokens'] > 100_000
            || $limits['max_output_tokens'] < 1 || $limits['max_output_tokens'] > 20_000
            || $limits['max_requests'] < 1 || $limits['max_requests'] > 50
            || $limits['max_wall_clock_seconds'] < 1 || $limits['max_wall_clock_seconds'] > 600) {
            throw new PolicyViolation('timeweb_probe_budget_missing', 'Positive bounded request, token, RUB and wall-clock probe caps are required.');
        }
    }

    private function validApiKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^[\x21-\x7e]{1,4096}$/D', $key) === 1;
    }
}
