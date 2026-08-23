<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Support\AiCanonicalJson;
use JsonException;
use Psr\Http\Message\RequestInterface;

final class OutreachCanaryHttpGuard
{
    private int $timewebRequests = 0;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $recipientMarker,
        private readonly OutreachCanaryContract $contract,
    ) {}

    public function authorize(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        if (mb_strtoupper($request->getMethod()) !== 'POST'
            || mb_strtolower($uri->getScheme()) !== 'https'
            || mb_strtolower($uri->getHost()) !== 'api.timeweb.ai'
            || ($uri->getPort() !== null && $uri->getPort() !== 443)
            || $uri->getPath() !== '/v1/responses'
            || $uri->getQuery() !== ''
            || $uri->getFragment() !== ''
            || $uri->getUserInfo() !== '') {
            throw new PolicyViolation('stage12b_http_target_blocked', 'Only one exact Timeweb Responses request is allowed.');
        }
        if ($this->timewebRequests >= 1) {
            throw new PolicyViolation('stage12b_http_request_cap_exceeded', 'The Stage 12B Timeweb request cap is exhausted.');
        }

        $body = (string) $request->getBody();
        if ($body === '' || strlen($body) > 65_536) {
            throw new PolicyViolation('stage12b_http_payload_blocked', 'The Timeweb request body is absent or oversized.');
        }
        try {
            $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PolicyViolation('stage12b_http_payload_blocked', 'The Timeweb request body is not valid JSON.');
        }
        if (! is_array($payload)) {
            throw new PolicyViolation('stage12b_http_payload_blocked', 'The Timeweb request body has an invalid root.');
        }
        $expectedKeys = ['input', 'instructions', 'max_output_tokens', 'model', 'store', 'text'];
        $actualKeys = array_keys($payload);
        sort($expectedKeys);
        sort($actualKeys);
        if ($actualKeys !== $expectedKeys
            || ($payload['model'] ?? null) !== OutreachCanaryContract::MODEL_ID
            || ($payload['store'] ?? null) !== false
            || ($payload['max_output_tokens'] ?? null) !== min(
                max(1, (int) config('ai-sales.providers.timeweb.probe.max_output_tokens')),
                OutreachCanaryContract::MAX_OUTPUT_TOKENS,
            )
            || data_get($payload, 'text.format.type') !== 'json_schema'
            || data_get($payload, 'text.format.name') !== OutreachCanaryContract::WIRE_SCHEMA_NAME
            || data_get($payload, 'text.format.strict') !== true
            || data_get($payload, 'text.format.schema') !== $this->contract->responseSchema()
            || array_key_exists('previous_response_id', $payload)
            || array_key_exists('tools', $payload)
            || array_key_exists('tool_choice', $payload)) {
            throw new PolicyViolation('stage12b_http_payload_blocked', 'The Timeweb wire payload differs from the fixed canary contract.');
        }

        if (! hash_equals('Bearer '.$this->apiKey, $request->getHeaderLine('Authorization'))) {
            throw new PolicyViolation('stage12b_http_auth_blocked', 'The external Timeweb route key was not bound correctly.');
        }

        $encoded = AiCanonicalJson::encode($payload);
        if (($this->recipientMarker !== '' && str_contains(mb_strtolower($encoded), mb_strtolower($this->recipientMarker)))
            || ($this->apiKey !== '' && str_contains($encoded, $this->apiKey))) {
            throw new PolicyViolation('stage12b_http_dlp_blocked', 'Secret or recipient data appeared in the provider body.');
        }

        $this->timewebRequests++;

        return $request;
    }

    /** @return array<string, int> */
    public function summary(): array
    {
        return [
            'timeweb_requests' => $this->timewebRequests,
            'yandex_requests' => 0,
            'other_live_http' => 0,
            'retries' => 0,
            'failovers' => 0,
        ];
    }
}
