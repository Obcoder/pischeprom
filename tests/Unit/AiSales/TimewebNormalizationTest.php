<?php

namespace Tests\Unit\AiSales;

use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Infrastructure\AiSales\Timeweb\TimewebGatewayResponse;
use App\Infrastructure\AiSales\Timeweb\TimewebProviderResponseNormalizer;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticSchemaValidator;
use App\Infrastructure\AiSales\Timeweb\TimewebTransportException;
use App\Infrastructure\AiSales\Timeweb\TimewebUsageNormalizer;
use Tests\TestCase;

class TimewebNormalizationTest extends TestCase
{
    public function test_chat_refusal_and_incomplete_are_distinct_normalized_states(): void
    {
        $normalizer = new TimewebProviderResponseNormalizer(new TimewebUsageNormalizer);
        $refusal = $normalizer->chat(new TimewebGatewayResponse(200, [
            'model' => 'synthetic/model',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => null, 'refusal' => 'Synthetic refusal.'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
        ], 'safe-refusal-id', 100), AiProviderRoute::ExternalSanitized, 'synthetic/model');
        $incomplete = $normalizer->chat(new TimewebGatewayResponse(200, [
            'model' => 'synthetic/model',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Partial synthetic text.'],
                'finish_reason' => 'length',
            ]],
            'usage' => [],
        ], null, 100), AiProviderRoute::ExternalSanitized, 'synthetic/model');

        $this->assertSame(AiProviderResponseStatus::Refused, $refusal->status);
        $this->assertSame('timeweb_model_refusal', $refusal->error?->safeCode);
        $this->assertSame(AiProviderResponseStatus::Incomplete, $incomplete->status);
        $this->assertNull($incomplete->usage->inputTokens);
        $this->assertNull($incomplete->usage->outputTokens);
    }

    public function test_responses_tool_call_and_usage_are_normalized_without_provider_object(): void
    {
        $normalizer = new TimewebProviderResponseNormalizer(new TimewebUsageNormalizer);
        $response = $normalizer->responses(new TimewebGatewayResponse(200, [
            'model' => 'synthetic/model',
            'status' => 'completed',
            'output' => [[
                'type' => 'function_call',
                'call_id' => 'call-safe-1',
                'name' => 'catalog.get_synthetic_good',
                'arguments' => '{"sku":"SYN-001"}',
            ]],
            'usage' => [
                'input_tokens' => 9,
                'output_tokens' => 4,
                'input_tokens_details' => ['cached_tokens' => 2],
                'output_tokens_details' => ['reasoning_tokens' => 3],
            ],
        ], 'safe-responses-id', 200), AiProviderRoute::LocalRu, 'synthetic/model');

        $this->assertSame(AiProviderResponseStatus::RequiresAction, $response->status);
        $this->assertSame('call-safe-1', $response->toolCalls[0]->callId);
        $this->assertSame(['sku' => 'SYN-001'], $response->toolCalls[0]->arguments);
        $this->assertSame(2, $response->usage->cachedTokens);
        $this->assertSame(3, $response->usage->reasoningTokens);
        $this->assertIsArray($response->outputItems);
    }

    public function test_model_mismatch_fails_closed_with_no_raw_body_in_exception(): void
    {
        $normalizer = new TimewebProviderResponseNormalizer(new TimewebUsageNormalizer);

        try {
            $normalizer->chat(new TimewebGatewayResponse(200, [
                'model' => 'attacker/model',
                'choices' => [],
                'raw_secret' => 'must-not-escape',
            ], null, 100), AiProviderRoute::ExternalSanitized, 'expected/model');
            $this->fail('Model mismatch must fail closed.');
        } catch (TimewebTransportException $exception) {
            $this->assertSame('timeweb_model_mismatch', $exception->safeCode);
            $this->assertStringNotContainsString('must-not-escape', $exception->getMessage());
            $this->assertStringNotContainsString('attacker/model', $exception->getMessage());
        }
    }

    public function test_completed_response_without_normalized_output_fails_closed(): void
    {
        $normalizer = new TimewebProviderResponseNormalizer(new TimewebUsageNormalizer);

        foreach (['chat', 'responses'] as $kind) {
            try {
                if ($kind === 'chat') {
                    $normalizer->chat(new TimewebGatewayResponse(200, [
                        'model' => 'synthetic/model',
                        'choices' => [[
                            'message' => ['role' => 'assistant', 'content' => null],
                            'finish_reason' => 'stop',
                        ]],
                    ], null, 100), AiProviderRoute::ExternalSanitized, 'synthetic/model');
                } else {
                    $normalizer->responses(new TimewebGatewayResponse(200, [
                        'model' => 'synthetic/model',
                        'status' => 'completed',
                        'output' => [['type' => 'reasoning']],
                    ], null, 100), AiProviderRoute::ExternalSanitized, 'synthetic/model');
                }

                $this->fail("Empty {$kind} output must fail closed.");
            } catch (TimewebTransportException $exception) {
                $this->assertSame(
                    $kind === 'chat' ? 'timeweb_chat_output_missing' : 'timeweb_responses_output_missing',
                    $exception->safeCode,
                );
            }
        }
    }

    public function test_strict_schema_validation_is_order_independent_but_rejects_object_keywords(): void
    {
        $schema = new TimewebSyntheticSchemaValidator;

        $this->assertTrue($schema->valid('{"keywords":["fixture"],"confidence":0.5,"category":"synthetic"}'));
        $this->assertFalse($schema->valid('{"category":"synthetic","confidence":0.5,"keywords":{"first":"fixture"}}'));
    }
}
