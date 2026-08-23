<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderInputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiProviderUsage;
use App\Domain\AiSales\Enums\AiCapabilitySupportStatus;
use App\Domain\AiSales\Enums\AiCapabilityVerificationStatus;
use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\AiResidencyAuthorizationService;
use App\Models\AiProviderCapability;
use App\Models\AiProviderModel;
use Illuminate\Support\Facades\DB;

class TimewebSyntheticProbeService
{
    private const PROFILES = ['all', 'basic', 'responses', 'structured', 'tools', 'store'];

    public function __construct(
        private readonly TimewebAiGatewayConfiguration $configuration,
        private readonly TimewebAiGatewayTransport $transport,
        private readonly TimewebModelSelector $models,
        private readonly TimewebSyntheticRequestFactory $requests,
        private readonly TimewebSyntheticFixtureRegistry $fixtures,
        private readonly TimewebSyntheticDlpGuard $dlp,
        private readonly TimewebRequestMapper $mapper,
        private readonly TimewebProviderResponseNormalizer $normalizer,
        private readonly TimewebSyntheticSchemaValidator $schema,
        private readonly TimewebProbeCostEstimator $costs,
        private readonly AiResidencyAuthorizationService $residency,
    ) {}

    public function run(
        AiProviderRoute $route,
        string $modelId,
        string $profile,
        bool $recordEvidence,
        string $operatorReference,
        TimewebProbeBudgetGuard $budget,
    ): TimewebSyntheticProbeResult {
        if (! in_array($profile, self::PROFILES, true)) {
            throw new PolicyViolation('timeweb_probe_profile_invalid', 'Probe profile is not in the code-owned allowlist.');
        }

        $operatorReference = $this->operatorReference($operatorReference);
        $this->configuration->assertProbeReady($route);
        $this->models->assertAllowed($route, $modelId);
        $inventory = AiProviderModel::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $route->value)
            ->where('model_id', $modelId)
            ->where('active_in_inventory', true)
            ->first();

        if (! $inventory) {
            throw new PolicyViolation('timeweb_model_inventory_missing', 'Exact model must be present in current normalized inventory before probing.');
        }

        if ($route === AiProviderRoute::LocalRu) {
            $this->residency->authorize('timeweb', $route->value, $modelId);
        }

        $results = [];
        $basic = null;

        if (in_array($profile, ['all', 'basic'], true)) {
            $basic = $this->probeChat($route, $modelId, $budget);
            $results = [...$results, ...$basic];
        }

        if (in_array($profile, ['all', 'responses'], true)) {
            $results['responses'] = $this->probeResponses($route, $modelId, $budget);
        }

        if (in_array($profile, ['all', 'structured'], true)) {
            $results['strict_structured_outputs'] = $this->probeStructured($route, $modelId, $budget);
        }

        if (in_array($profile, ['all', 'tools'], true)) {
            $results['function_calling'] = $this->probeTools($route, $modelId, $budget);
        }

        if (in_array($profile, ['all', 'store'], true)) {
            $results['store_false'] = $this->probeStoreFalse($route, $modelId, $budget);
        }

        if ($profile === 'all') {
            $results['hosted_web_search'] = $this->outcome(
                AiCapabilitySupportStatus::Unsupported,
                null,
                null,
                'official-docs:no-ai-gateway-web-search-contract',
            );
        }

        ksort($results);
        $resultHash = hash('sha256', json_encode([
            'provider' => 'timeweb',
            'route' => $route->value,
            'model' => $modelId,
            'capabilities' => $results,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($recordEvidence) {
            $this->record($route, $modelId, $results, $operatorReference, $resultHash);
            $this->updateEndpointProfile($inventory, $results, $operatorReference);
        }

        return new TimewebSyntheticProbeResult(
            $route->value,
            $modelId,
            $results,
            $budget->summary(),
            $recordEvidence,
            $resultHash,
        );
    }

    private function probeChat(AiProviderRoute $route, string $modelId, TimewebProbeBudgetGuard $budget): array
    {
        $request = $this->request($route, 'public_basic', ['chat_completions'], false);
        $result = $this->performChat($route, $modelId, $request, $budget);

        if ($result instanceof TimewebTransportException) {
            throw $result;
        }

        return [
            'chat_completions' => $this->responseOutcome($result),
            'usage_reporting' => $this->outcome(
                $result->usage->inputTokens !== null && $result->usage->outputTokens !== null
                    ? AiCapabilitySupportStatus::Supported
                    : AiCapabilitySupportStatus::Unknown,
                $result,
                null,
                'normalized_usage_fields',
            ),
            'request_id' => $this->outcome(
                $result->requestId !== null ? AiCapabilitySupportStatus::Supported : AiCapabilitySupportStatus::Unknown,
                $result,
                null,
                'safe_provider_request_id_header',
            ),
        ];
    }

    private function probeResponses(AiProviderRoute $route, string $modelId, TimewebProbeBudgetGuard $budget): array
    {
        $request = $this->request($route, 'public_basic', ['responses'], false);
        $result = $this->performResponses($route, $modelId, $request, $budget);

        if ($result instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($result, [404, 405, 422]);
        }

        $normalizedPriorItems = array_map(static fn ($item): array => [
            'type' => $item->type,
            'text' => is_string($item->data['text'] ?? null)
                ? mb_substr($item->data['text'], 0, 1000)
                : null,
        ], array_slice($result->outputItems, 0, 8));
        $secondRequest = $this->request(
            $route,
            'public_basic',
            ['responses'],
            false,
            [],
            [new AiProviderInputItem('sanitized_data', 'normalized_prior_response_items', [
                'items' => $normalizedPriorItems,
            ])],
        );
        $second = $this->performResponses($route, $modelId, $secondRequest, $budget);

        if ($second instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($second, [404, 405, 422]);
        }

        return $this->responseOutcome($second, 'two_requests_reconstructed_from_local_normalized_items_without_previous_response_id');
    }

    private function probeStructured(AiProviderRoute $route, string $modelId, TimewebProbeBudgetGuard $budget): array
    {
        $request = $this->request($route, 'public_structured', ['chat_completions', 'strict_structured_outputs'], false);
        $result = $this->performChat($route, $modelId, $request, $budget);

        if ($result instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($result, [400, 404, 405, 422]);
        }

        $text = $result->outputItems[0]->data['text'] ?? null;

        return $this->outcome(
            $this->schema->valid($text) ? AiCapabilitySupportStatus::Supported : AiCapabilitySupportStatus::Unsupported,
            $result,
            null,
            'native_json_schema_probe',
        );
    }

    private function probeTools(AiProviderRoute $route, string $modelId, TimewebProbeBudgetGuard $budget): array
    {
        $request = $this->request(
            $route,
            'public_tool',
            ['chat_completions', 'function_calling'],
            false,
            [$this->fixtures->toolSchema()],
        );
        $first = $this->performChat($route, $modelId, $request, $budget);

        if ($first instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($first, [400, 404, 405, 422]);
        }

        if ($first->status !== AiProviderResponseStatus::RequiresAction || count($first->toolCalls) !== 1) {
            return $this->outcome(AiCapabilitySupportStatus::Unsupported, $first, null, 'native_tool_call_absent');
        }

        $call = $first->toolCalls[0];

        try {
            $toolOutput = $this->fixtures->executeTool($call->toolCode, $call->arguments);
        } catch (PolicyViolation) {
            return $this->outcome(AiCapabilitySupportStatus::Unsupported, $first, null, 'tool_arguments_failed_strict_validation');
        }

        $second = $this->request(
            $route,
            'public_tool',
            ['chat_completions', 'function_calling'],
            false,
            [$this->fixtures->toolSchema()],
            [
                new AiProviderInputItem('assistant_tool_call', 'normalized_synthetic_tool_call', [
                    'call_id' => $call->callId,
                    'tool_code' => $call->toolCode,
                    'arguments' => $call->arguments,
                ]),
                new AiProviderInputItem('tool_result', 'local_synthetic_tool_result', [
                    'call_id' => $call->callId,
                    'tool_code' => $call->toolCode,
                    'output' => $toolOutput,
                ]),
            ],
        );
        $final = $this->performChat($route, $modelId, $second, $budget);

        if ($final instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($final, [400, 404, 405, 422]);
        }

        return $this->outcome(
            $final->status === AiProviderResponseStatus::Completed
                ? AiCapabilitySupportStatus::Supported
                : AiCapabilitySupportStatus::Unsupported,
            $final,
            null,
            'strict_local_synthetic_tool_cycle',
        );
    }

    private function probeStoreFalse(AiProviderRoute $route, string $modelId, TimewebProbeBudgetGuard $budget): array
    {
        $request = $this->request($route, 'public_basic', ['chat_completions', 'store_false'], false);
        $result = $this->performChat($route, $modelId, $request, $budget);

        if ($result instanceof TimewebTransportException) {
            return $this->unsupportedOrThrow($result, [400, 405, 422]);
        }

        return $this->responseOutcome($result, 'parameter_accepted_retention_not_confirmed');
    }

    private function request(
        AiProviderRoute $route,
        string $fixture,
        array $capabilities,
        bool $localOnly,
        array $tools = [],
        array $additionalItems = [],
    ): AiProviderRequest {
        $limits = $this->configuration->probeLimits();

        return $this->requests->make(
            $route,
            AiModelProfile::StandardResearch,
            $fixture,
            $capabilities,
            min(1000, $limits['max_input_tokens']),
            min(256, $limits['max_output_tokens']),
            $tools,
            $additionalItems,
        );
    }

    private function performChat(
        AiProviderRoute $route,
        string $modelId,
        AiProviderRequest $request,
        TimewebProbeBudgetGuard $budget,
    ): AiProviderResponse|TimewebTransportException {
        return $this->perform($route, $modelId, $request, $budget, false);
    }

    private function performResponses(
        AiProviderRoute $route,
        string $modelId,
        AiProviderRequest $request,
        TimewebProbeBudgetGuard $budget,
    ): AiProviderResponse|TimewebTransportException {
        return $this->perform($route, $modelId, $request, $budget, true);
    }

    private function perform(
        AiProviderRoute $route,
        string $modelId,
        AiProviderRequest $request,
        TimewebProbeBudgetGuard $budget,
        bool $responses,
    ): AiProviderResponse|TimewebTransportException {
        $this->dlp->authorize($request);
        $reserved = $this->costs->maximum(
            $route,
            $modelId,
            $request->requirements->maxInputTokens,
            $request->requirements->maxOutputTokens,
        );
        $budget->reserve(
            $request->requirements->maxInputTokens,
            $request->requirements->maxOutputTokens,
            $reserved,
        );

        try {
            $response = $responses
                ? $this->normalizer->responses(
                    $this->transport->responses(
                        $route,
                        $this->mapper->responses($request, $modelId),
                        $budget->remainingTimeoutSeconds(),
                    ),
                    $route,
                    $modelId,
                )
                : $this->normalizer->chat(
                    $this->transport->chatCompletions(
                        $route,
                        $this->mapper->chatCompletions($request, $modelId),
                        $budget->remainingTimeoutSeconds(),
                    ),
                    $route,
                    $modelId,
                );
            $budget->reconcile($response->usage);

            return $this->withCost($response, $this->costs->actualOrReserved($route, $modelId, $response->usage, $reserved));
        } catch (TimewebTransportException $exception) {
            return $exception;
        }
    }

    private function withCost(AiProviderResponse $response, string $rub): AiProviderResponse
    {
        return new AiProviderResponse(
            $response->status,
            $response->providerCode,
            $response->providerRoute,
            $response->modelId,
            $response->requestId,
            $response->outputItems,
            $response->toolCalls,
            $response->citations,
            new AiProviderUsage(
                $response->usage->inputTokens,
                $response->usage->outputTokens,
                $response->usage->reasoningTokens,
                $response->usage->cachedTokens,
                $response->usage->searchCount,
                $response->usage->toolCallCount,
                null,
                null,
                $rub,
            ),
            $response->error,
        );
    }

    private function responseOutcome(AiProviderResponse $response, string $note = 'normalized_response'): array
    {
        return $this->outcome(
            $response->status === AiProviderResponseStatus::Failed
                ? AiCapabilitySupportStatus::Unknown
                : AiCapabilitySupportStatus::Supported,
            $response,
            null,
            $note,
        );
    }

    private function unsupportedOrThrow(TimewebTransportException $error, array $unsupportedStatuses): array
    {
        if (! in_array($error->statusCode, $unsupportedStatuses, true)) {
            throw $error;
        }

        return $this->outcome(AiCapabilitySupportStatus::Unsupported, null, $error, $error->safeCode);
    }

    private function outcome(
        AiCapabilitySupportStatus $support,
        ?AiProviderResponse $response,
        ?TimewebTransportException $error,
        string $note,
    ): array {
        return [
            'support' => $support->value,
            'request_id' => $response?->requestId ?? $error?->requestId,
            'safe_error_code' => $error?->safeCode,
            'input_tokens' => $response?->usage->inputTokens,
            'output_tokens' => $response?->usage->outputTokens,
            'reasoning_tokens' => $response?->usage->reasoningTokens,
            'estimated_rub' => $response?->usage->normalizedRubAmount,
            'note' => mb_substr($note, 0, 191),
        ];
    }

    private function record(
        AiProviderRoute $route,
        string $modelId,
        array $results,
        string $operatorReference,
        string $aggregateHash,
    ): void {
        DB::transaction(function () use ($route, $modelId, $results, $operatorReference, $aggregateHash): void {
            foreach ($results as $capability => $result) {
                $support = AiCapabilitySupportStatus::from($result['support']);
                $documented = $result['note'] === 'official-docs:no-ai-gateway-web-search-contract';
                $resultHash = hash('sha256', json_encode([
                    'route' => $route->value,
                    'model' => $modelId,
                    'capability' => $capability,
                    'result' => $result,
                    'aggregate' => $aggregateHash,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                AiProviderCapability::query()->updateOrCreate([
                    'provider_code' => 'timeweb',
                    'provider_route' => $route->value,
                    'model_id' => $modelId,
                    'capability' => $capability,
                ], [
                    'contour' => $route->contour(),
                    'status' => $documented
                        ? AiCapabilityVerificationStatus::Documented
                        : AiCapabilityVerificationStatus::SyntheticTested,
                    'support_state' => $support,
                    'max_context_tokens' => $this->configuration->probeLimits()['max_input_tokens'],
                    'max_output_tokens' => $this->configuration->probeLimits()['max_output_tokens'],
                    'evidence_reference' => 'timeweb:stage05:'.($result['request_id'] ?? substr($resultHash, 0, 16)),
                    'evidence_hash' => $resultHash,
                    'evidence_source' => $documented ? 'public_documentation' : 'synthetic_live_probe',
                    'safe_request_id' => $result['request_id'],
                    'adapter_version' => (string) config('ai-sales.providers.timeweb.adapter_version', 'stage05-v1'),
                    'policy_version' => 'stage05-synthetic-only-v1',
                    'schema_version' => 'stage05-probe-v1',
                    'result_hash' => $resultHash,
                    'operator_reference' => $operatorReference,
                    'verified_by' => null,
                    'verified_at' => now(),
                    'expires_at' => now()->addDays(min(30, max(1, (int) config('ai-sales.providers.timeweb.probe.residency_expiry_days', 30)))),
                    'probe_version' => 'timeweb-stage05-v1',
                ]);
            }
        }, 3);
    }

    private function updateEndpointProfile(AiProviderModel $model, array $results, string $operatorReference): void
    {
        $responses = data_get($results, 'responses.support') ?? AiProviderCapability::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $model->provider_route)
            ->where('model_id', $model->model_id)
            ->where('capability', 'responses')
            ->value('support_state');
        $chat = data_get($results, 'chat_completions.support') ?? AiProviderCapability::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $model->provider_route)
            ->where('model_id', $model->model_id)
            ->where('capability', 'chat_completions')
            ->value('support_state');
        $profile = match (true) {
            $responses === AiCapabilitySupportStatus::Supported->value => AiProviderEndpointProfile::Responses,
            $chat === AiCapabilitySupportStatus::Supported->value => AiProviderEndpointProfile::ChatCompletions,
            default => AiProviderEndpointProfile::Unsupported,
        };

        $model->update([
            'endpoint_profile' => $profile,
            'updated_by_reference' => $operatorReference,
        ]);
    }

    private function operatorReference(string $value): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 128
            || preg_match('/^[A-Za-z0-9._:@-]+$/', $value) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session|private/i', $value) === 1
            || $this->containsConfiguredKey($value)) {
            throw new PolicyViolation('timeweb_operator_reference_invalid', 'A bounded non-secret operator reference is required.');
        }

        return $value;
    }

    private function containsConfiguredKey(string $value): bool
    {
        foreach (AiProviderRoute::cases() as $route) {
            $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

            if (is_string($key) && $key !== '' && hash_equals($key, $value)) {
                return true;
            }
        }

        return false;
    }
}
