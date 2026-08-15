<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderError;
use App\Domain\AiSales\DTO\Providers\AiProviderOutputItem;
use App\Domain\AiSales\DTO\Providers\AiProviderResponse;
use App\Domain\AiSales\DTO\Providers\AiProviderToolCall;
use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use App\Domain\AiSales\Enums\AiProviderResponseStatus;
use App\Domain\AiSales\Enums\AiProviderRoute;
use JsonException;

class TimewebProviderResponseNormalizer
{
    public function __construct(private readonly TimewebUsageNormalizer $usage) {}

    public function chat(
        TimewebGatewayResponse $wire,
        AiProviderRoute $route,
        string $expectedModelId,
    ): AiProviderResponse {
        $this->assertModel($wire->data, $expectedModelId);
        $choice = $wire->data['choices'][0] ?? null;
        $message = is_array($choice) && is_array($choice['message'] ?? null) ? $choice['message'] : null;

        if (! is_array($choice) || ! is_array($message)) {
            throw $this->invalid('timeweb_chat_shape_invalid', $wire);
        }

        $outputs = [];
        $toolCalls = $this->chatToolCalls($message['tool_calls'] ?? [], $wire);
        $refusal = $this->boundedText($message['refusal'] ?? null);
        $content = $this->boundedText($message['content'] ?? null);

        if ($refusal !== null) {
            $outputs[] = new AiProviderOutputItem('refusal', ['text' => $refusal]);
        } elseif ($content !== null) {
            $outputs[] = new AiProviderOutputItem('text', ['text' => $content]);
        }

        if ($outputs === [] && $toolCalls === []) {
            throw $this->invalid('timeweb_chat_output_missing', $wire);
        }

        $finishReason = is_string($choice['finish_reason'] ?? null) ? $choice['finish_reason'] : null;
        $status = match (true) {
            $refusal !== null => AiProviderResponseStatus::Refused,
            $toolCalls !== [] => AiProviderResponseStatus::RequiresAction,
            in_array($finishReason, ['length', 'content_filter'], true) => AiProviderResponseStatus::Incomplete,
            default => AiProviderResponseStatus::Completed,
        };

        return new AiProviderResponse(
            $status,
            'timeweb',
            $route->value,
            $expectedModelId,
            $wire->requestId,
            $outputs,
            $toolCalls,
            [],
            $this->usage->chat(is_array($wire->data['usage'] ?? null) ? $wire->data['usage'] : [], count($toolCalls)),
            $status === AiProviderResponseStatus::Refused
                ? new AiProviderError(AiProviderErrorCategory::PolicyBlocked, 'timeweb_model_refusal', 'The model refused the synthetic request.')
                : null,
        );
    }

    public function responses(
        TimewebGatewayResponse $wire,
        AiProviderRoute $route,
        string $expectedModelId,
    ): AiProviderResponse {
        $this->assertModel($wire->data, $expectedModelId);
        $output = $wire->data['output'] ?? null;

        if (! is_array($output)) {
            throw $this->invalid('timeweb_responses_shape_invalid', $wire);
        }

        $items = [];
        $toolCalls = [];
        $refused = false;

        foreach (array_slice($output, 0, 32) as $wireItem) {
            if (! is_array($wireItem)) {
                continue;
            }

            if (($wireItem['type'] ?? null) === 'function_call') {
                $toolCalls[] = $this->responsesToolCall($wireItem, $wire);

                continue;
            }

            if (($wireItem['type'] ?? null) !== 'message' || ! is_array($wireItem['content'] ?? null)) {
                continue;
            }

            foreach (array_slice($wireItem['content'], 0, 32) as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    $text = $this->boundedText($content['refusal'] ?? null);

                    if ($text !== null) {
                        $items[] = new AiProviderOutputItem('refusal', ['text' => $text]);
                        $refused = true;
                    }
                }

                if (($content['type'] ?? null) === 'output_text') {
                    $text = $this->boundedText($content['text'] ?? null);

                    if ($text !== null) {
                        $items[] = new AiProviderOutputItem('text', ['text' => $text]);
                    }
                }
            }
        }

        $wireStatus = is_string($wire->data['status'] ?? null) ? $wire->data['status'] : 'completed';
        $status = match (true) {
            $refused => AiProviderResponseStatus::Refused,
            $toolCalls !== [] => AiProviderResponseStatus::RequiresAction,
            $wireStatus === 'incomplete' => AiProviderResponseStatus::Incomplete,
            $wireStatus === 'failed' => AiProviderResponseStatus::Failed,
            default => AiProviderResponseStatus::Completed,
        };

        if ($status === AiProviderResponseStatus::Completed && $items === [] && $toolCalls === []) {
            throw $this->invalid('timeweb_responses_output_missing', $wire);
        }

        return new AiProviderResponse(
            $status,
            'timeweb',
            $route->value,
            $expectedModelId,
            $wire->requestId,
            $items,
            $toolCalls,
            [],
            $this->usage->responses(is_array($wire->data['usage'] ?? null) ? $wire->data['usage'] : [], count($toolCalls)),
            match ($status) {
                AiProviderResponseStatus::Refused => new AiProviderError(AiProviderErrorCategory::PolicyBlocked, 'timeweb_model_refusal', 'The model refused the synthetic request.'),
                AiProviderResponseStatus::Failed => new AiProviderError(AiProviderErrorCategory::ProviderUnavailable, 'timeweb_response_failed', 'The provider returned a normalized failed response.'),
                default => null,
            },
        );
    }

    public function failure(TimewebTransportException $exception, AiProviderRoute $route, string $modelId): AiProviderResponse
    {
        return new AiProviderResponse(
            AiProviderResponseStatus::Failed,
            'timeweb',
            $route->value,
            $modelId,
            $exception->requestId,
            [],
            [],
            [],
            new \App\Domain\AiSales\DTO\Providers\AiProviderUsage,
            new AiProviderError(
                $exception->category,
                $exception->safeCode,
                'Timeweb AI Gateway request failed safely.',
                $exception->retryable,
            ),
        );
    }

    private function chatToolCalls(mixed $value, TimewebGatewayResponse $wire): array
    {
        if (! is_array($value)) {
            return [];
        }

        $calls = [];

        foreach (array_slice($value, 0, 8) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'function' || ! is_array($item['function'] ?? null)) {
                throw $this->invalid('timeweb_tool_call_shape_invalid', $wire);
            }

            $calls[] = $this->toolCall(
                $item['id'] ?? null,
                $item['function']['name'] ?? null,
                $item['function']['arguments'] ?? null,
                $wire,
            );
        }

        return $calls;
    }

    private function responsesToolCall(array $item, TimewebGatewayResponse $wire): AiProviderToolCall
    {
        return $this->toolCall(
            $item['call_id'] ?? $item['id'] ?? null,
            $item['name'] ?? null,
            $item['arguments'] ?? null,
            $wire,
        );
    }

    private function toolCall(mixed $callId, mixed $name, mixed $arguments, TimewebGatewayResponse $wire): AiProviderToolCall
    {
        if (! is_string($callId) || preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $callId) !== 1
            || $name !== 'catalog.get_synthetic_good'
            || ! is_string($arguments) || strlen($arguments) > 4096) {
            throw $this->invalid('timeweb_tool_call_blocked', $wire);
        }

        try {
            $decoded = json_decode($arguments, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid('timeweb_tool_arguments_invalid', $wire);
        }

        if (! is_array($decoded)) {
            throw $this->invalid('timeweb_tool_arguments_invalid', $wire);
        }

        try {
            $argumentsHash = hash('sha256', json_encode(
                $decoded,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } catch (JsonException) {
            throw $this->invalid('timeweb_tool_arguments_invalid', $wire);
        }

        return new AiProviderToolCall(
            $callId,
            $name,
            '1',
            $decoded,
            $argumentsHash,
        );
    }

    private function assertModel(array $data, string $expected): void
    {
        $actual = $data['model'] ?? null;

        if (! is_string($actual) || ! hash_equals($expected, $actual)) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::InvalidResponse,
                'timeweb_model_mismatch',
            );
        }
    }

    private function boundedText(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) > 32_768) {
            throw new TimewebTransportException(
                AiProviderErrorCategory::OversizedResponse,
                'timeweb_output_item_too_large',
            );
        }

        return $value;
    }

    private function invalid(string $code, TimewebGatewayResponse $wire): TimewebTransportException
    {
        return new TimewebTransportException(
            AiProviderErrorCategory::InvalidResponse,
            $code,
            false,
            $wire->statusCode,
            $wire->requestId,
        );
    }
}
