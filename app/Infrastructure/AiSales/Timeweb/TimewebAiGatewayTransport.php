<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderRoute;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class TimewebAiGatewayTransport
{
    private const ALLOWED_PATHS = [
        'models' => '/models',
        'responses' => '/responses',
        'chat_completions' => '/chat/completions',
    ];

    public function __construct(
        private readonly TimewebAiGatewayConfiguration $configuration,
        private readonly TimewebModelSelector $models,
    ) {}

    public function listModels(AiProviderRoute $route, ?float $timeoutSeconds = null): TimewebGatewayResponse
    {
        return $this->request($route, 'models', 'GET', [], $timeoutSeconds);
    }

    public function chatCompletions(AiProviderRoute $route, array $payload, ?float $timeoutSeconds = null): TimewebGatewayResponse
    {
        $this->assertModelPayload($route, $payload, [
            'model', 'messages', 'stream', 'store', 'max_completion_tokens',
            'response_format', 'tools', 'tool_choice',
        ]);

        return $this->request($route, 'chat_completions', 'POST', $payload, $timeoutSeconds);
    }

    public function responses(AiProviderRoute $route, array $payload, ?float $timeoutSeconds = null): TimewebGatewayResponse
    {
        $this->assertModelPayload($route, $payload, [
            'model', 'instructions', 'input', 'store', 'max_output_tokens',
            'text', 'tools', 'tool_choice',
        ]);

        return $this->request($route, 'responses', 'POST', $payload, $timeoutSeconds);
    }

    private function request(
        AiProviderRoute $route,
        string $endpoint,
        string $method,
        array $payload = [],
        ?float $timeoutSeconds = null,
    ): TimewebGatewayResponse {
        $this->configuration->assertProbeReady($route);
        $path = self::ALLOWED_PATHS[$endpoint] ?? throw new TimewebTransportException(
            AiProviderErrorCategory::PolicyBlocked,
            'timeweb_endpoint_blocked',
        );
        $baseUrl = $this->configuration->assertBaseUrl();
        $correlationId = (string) Str::uuid();

        try {
            $pending = $this->pendingRequest($route, $correlationId, $timeoutSeconds);
            $response = $method === 'GET'
                ? $pending->get($baseUrl.$path)
                : $pending->post($baseUrl.$path, $payload);

            return $this->normalizeResponse($response);
        } catch (TimewebTransportException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        } catch (Throwable $exception) {
            if ($this->hasSafeMarker($exception, 'timeweb_response_too_large')) {
                throw new TimewebTransportException(
                    AiProviderErrorCategory::OversizedResponse,
                    'timeweb_response_too_large',
                );
            }

            throw new TimewebTransportException(
                AiProviderErrorCategory::Network,
                'timeweb_network_failure',
                true,
            );
        }
    }

    private function pendingRequest(AiProviderRoute $route, string $correlationId, ?float $timeoutSeconds): PendingRequest
    {
        $timeout = $timeoutSeconds === null
            ? $this->configuration->timeout()
            : min((float) $this->configuration->timeout(), $timeoutSeconds);

        if ($timeout <= 0) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::PolicyBlocked,
                'timeweb_request_timeout_exhausted',
            );
        }

        return Http::asJson()
            ->acceptJson()
            ->withToken($this->configuration->apiKey($route))
            ->withUserAgent((string) config('ai-sales.providers.timeweb.user_agent', 'pischeprom-ai-sales/timeweb-stage05'))
            ->withHeaders([
                'X-Request-ID' => $correlationId,
                'X-AI-Processing-Route' => $route->value,
            ])
            ->connectTimeout(min($this->configuration->connectTimeout(), $timeout))
            ->timeout($timeout)
            ->withOptions([
                'allow_redirects' => false,
                'verify' => true,
                'http_errors' => false,
                'read_timeout' => $timeout,
                'on_headers' => function ($response): void {
                    $length = $response->getHeaderLine('Content-Length');

                    if ($length !== '' && ctype_digit($length)
                        && (int) $length > $this->configuration->maxResponseBytes()) {
                        throw new \RuntimeException('timeweb_response_too_large');
                    }
                },
                'progress' => function (
                    int|float $downloadTotal,
                    int|float $downloadedBytes,
                    int|float $uploadTotal,
                    int|float $uploadedBytes,
                ): void {
                    if ($downloadedBytes > $this->configuration->maxResponseBytes()) {
                        throw new \RuntimeException('timeweb_response_too_large');
                    }
                },
            ]);
    }

    private function normalizeResponse(Response $response): TimewebGatewayResponse
    {
        $requestId = $this->safeRequestId(
            $response->header('X-Request-ID')
                ?: $response->header('X-Trace-ID'),
        );
        $body = $response->body();
        $bytes = strlen($body);

        if ($bytes > $this->configuration->maxResponseBytes()) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::OversizedResponse,
                'timeweb_response_too_large',
                false,
                $response->status(),
                $requestId,
            );
        }

        // Error bodies are deliberately neither parsed nor propagated.
        if (! $response->successful()) {
            throw $this->httpFailure($response->status(), $requestId);
        }

        $contentType = mb_strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if ($contentType !== 'application/json' && ! str_ends_with($contentType, '+json')) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::InvalidResponse,
                'timeweb_unexpected_content_type',
                false,
                $response->status(),
                $requestId,
            );
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::InvalidResponse,
                'timeweb_invalid_json',
                false,
                $response->status(),
                $requestId,
            );
        }

        if (! is_array($decoded)) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::InvalidResponse,
                'timeweb_invalid_json_shape',
                false,
                $response->status(),
                $requestId,
            );
        }

        return new TimewebGatewayResponse($response->status(), $decoded, $requestId, $bytes);
    }

    private function httpFailure(int $status, ?string $requestId): TimewebTransportException
    {
        [$category, $code, $retryable] = match ($status) {
            400 => [AiProviderErrorCategory::BadRequest, 'timeweb_bad_request', false],
            401, 403 => [AiProviderErrorCategory::Authentication, 'timeweb_authentication_failed', false],
            402 => [AiProviderErrorCategory::InsufficientBalance, 'timeweb_insufficient_balance', false],
            404 => [AiProviderErrorCategory::NotFound, 'timeweb_model_or_endpoint_not_found', false],
            405 => [AiProviderErrorCategory::UnsupportedEndpoint, 'timeweb_endpoint_unsupported', false],
            409 => [AiProviderErrorCategory::Conflict, 'timeweb_conflict', false],
            422 => [AiProviderErrorCategory::Unprocessable, 'timeweb_request_unsupported', false],
            429 => [AiProviderErrorCategory::RateLimited, 'timeweb_rate_limited', true],
            default => $status >= 500
                ? [AiProviderErrorCategory::ServerError, 'timeweb_server_error', true]
                : [AiProviderErrorCategory::ProviderUnavailable, 'timeweb_http_failure', false],
        };

        return new TimewebTransportException($category, $code, $retryable, $status, $requestId);
    }

    private function connectionFailure(ConnectionException $exception): TimewebTransportException
    {
        $class = mb_strtolower($exception::class.' '.($exception->getPrevious() ? $exception->getPrevious()::class : ''));
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($class, 'ssl') || str_contains($message, 'certificate') || str_contains($message, 'ssl')) {
            return new TimewebTransportException(AiProviderErrorCategory::Tls, 'timeweb_tls_failure', false);
        }

        if (str_contains($message, 'resolve host') || str_contains($message, 'getaddrinfo') || str_contains($message, 'dns')) {
            return new TimewebTransportException(AiProviderErrorCategory::Dns, 'timeweb_dns_failure', true);
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return new TimewebTransportException(AiProviderErrorCategory::Timeout, 'timeweb_timeout', true);
        }

        return new TimewebTransportException(AiProviderErrorCategory::Network, 'timeweb_connection_failure', true);
    }

    private function safeRequestId(mixed $value): ?string
    {
        if (! is_string($value)
            || preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session|private/i', $value) === 1
            || preg_match('/^eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}$/', $value) === 1) {
            return null;
        }

        foreach (AiProviderRoute::cases() as $route) {
            $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

            if (is_string($key) && $key !== '' && hash_equals($key, $value)) {
                return null;
            }
        }

        return $value;
    }

    private function assertModelPayload(AiProviderRoute $route, array $payload, array $allowedKeys): void
    {
        $modelId = $payload['model'] ?? null;

        if (! is_string($modelId)) {
            throw new \App\Domain\AiSales\Exceptions\PolicyViolation(
                'timeweb_model_missing',
                'Timeweb model calls require an exact server-owned model ID.',
            );
        }

        $this->models->assertAllowed($route, $modelId);

        if (array_diff(array_keys($payload), $allowedKeys) !== []
            || array_key_exists('previous_response_id', $payload)
            || ($payload['store'] ?? null) !== false
            || (($payload['stream'] ?? false) !== false)) {
            throw new \App\Domain\AiSales\Exceptions\PolicyViolation(
                'timeweb_wire_payload_blocked',
                'Timeweb wire payload contains a non-approved parameter or provider-state dependency.',
            );
        }
    }

    private function hasSafeMarker(Throwable $exception, string $marker): bool
    {
        do {
            if ($exception->getMessage() === $marker || str_contains($exception->getMessage(), $marker)) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception instanceof Throwable);

        return false;
    }
}
