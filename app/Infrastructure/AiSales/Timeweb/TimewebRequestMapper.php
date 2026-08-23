<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\DTO\Providers\AiProviderRequest;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use JsonException;

class TimewebRequestMapper
{
    public function chatCompletions(AiProviderRequest $request, string $modelId): array
    {
        $messages = [];

        foreach ($request->inputItems as $item) {
            if ($item->type === 'assistant_tool_call') {
                $messages[] = $this->assistantToolCallMessage($item->data);

                continue;
            }

            if ($item->type === 'tool_result') {
                $messages[] = $this->toolResultMessage($item->data);

                continue;
            }

            $messages[] = [
                'role' => $item->type === 'instruction' ? 'system' : 'user',
                'content' => $this->encodeItem($item->label, $item->data),
            ];
        }

        $payload = [
            'model' => $modelId,
            'messages' => $messages,
            'stream' => false,
            'store' => false,
            'max_completion_tokens' => $request->requirements->maxOutputTokens,
        ];

        if (in_array('strict_structured_outputs', $request->requirements->capabilities, true)) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $request->responseSchemaName,
                    'strict' => true,
                    'schema' => $request->responseSchema,
                ],
            ];
        }

        if ($request->toolSchemas !== []) {
            $payload['tools'] = array_map(fn (array $schema): array => $this->chatTool($schema), $request->toolSchemas);
            $payload['tool_choice'] = 'auto';
        }

        return $this->assertBounded($payload);
    }

    public function responses(AiProviderRequest $request, string $modelId): array
    {
        $instructions = [];
        $input = [];

        foreach ($request->inputItems as $item) {
            $encoded = $this->encodeItem($item->label, $item->data);

            if ($item->type === 'instruction') {
                $instructions[] = $encoded;
            } elseif ($item->type === 'assistant_tool_call') {
                $input[] = $this->responsesToolCallInput($item->data);
            } elseif ($item->type === 'tool_result') {
                $input[] = $this->responsesToolResultInput($item->data);
            } else {
                $input[] = ['role' => 'user', 'content' => $encoded];
            }
        }

        $payload = [
            'model' => $modelId,
            'instructions' => implode("\n", $instructions),
            'input' => $input,
            'store' => false,
            'max_output_tokens' => $request->requirements->maxOutputTokens,
        ];

        if (in_array('strict_structured_outputs', $request->requirements->capabilities, true)) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $request->responseSchemaName,
                    'strict' => true,
                    'schema' => $request->responseSchema,
                ],
            ];
        }

        if ($request->toolSchemas !== []) {
            $payload['tools'] = array_map(fn (array $schema): array => $this->responsesTool($schema), $request->toolSchemas);
            $payload['tool_choice'] = 'auto';
        }

        // Provider state is deliberately absent: previous_response_id is never mapped.
        return $this->assertBounded($payload);
    }

    private function encodeItem(string $label, array $data): string
    {
        try {
            $encoded = json_encode(
                ['fixture_label' => $label, 'data' => $data],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            throw new PolicyViolation('timeweb_input_encoding_failed', 'Synthetic provider input could not be encoded safely.');
        }

        if (strlen($encoded) > 49_152) {
            throw new PolicyViolation('timeweb_input_too_large', 'Synthetic provider input exceeds the Stage 05 byte cap.');
        }

        return $encoded;
    }

    private function chatTool(array $schema): array
    {
        $tool = $this->validatedTool($schema);

        return ['type' => 'function', 'function' => $tool];
    }

    private function assistantToolCallMessage(array $data): array
    {
        [$callId, $toolCode, $arguments] = $this->toolCycleData($data);

        return [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => $callId,
                'type' => 'function',
                'function' => [
                    'name' => $toolCode,
                    'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ];
    }

    private function toolResultMessage(array $data): array
    {
        [$callId, $_toolCode, $output] = $this->toolCycleData($data, 'output');

        return [
            'role' => 'tool',
            'tool_call_id' => $callId,
            'content' => json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function responsesToolCallInput(array $data): array
    {
        [$callId, $toolCode, $arguments] = $this->toolCycleData($data);

        return [
            'type' => 'function_call',
            'call_id' => $callId,
            'name' => $toolCode,
            'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function responsesToolResultInput(array $data): array
    {
        [$callId, $_toolCode, $output] = $this->toolCycleData($data, 'output');

        return [
            'type' => 'function_call_output',
            'call_id' => $callId,
            'output' => json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function toolCycleData(array $data, string $valueKey = 'arguments'): array
    {
        $callId = $data['call_id'] ?? null;
        $toolCode = $data['tool_code'] ?? null;
        $value = $data[$valueKey] ?? null;

        if (! is_string($callId) || preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $callId) !== 1
            || $toolCode !== 'catalog.get_synthetic_good'
            || ! is_array($value)) {
            throw new PolicyViolation('timeweb_tool_cycle_blocked', 'Synthetic tool cycle item does not match the fixed local contract.');
        }

        return [$callId, $toolCode, $value];
    }

    private function responsesTool(array $schema): array
    {
        $tool = $this->validatedTool($schema);

        return [
            'type' => 'function',
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $tool['parameters'],
            'strict' => true,
        ];
    }

    private function validatedTool(array $schema): array
    {
        $name = $schema['name'] ?? null;
        $description = $schema['description'] ?? null;
        $parameters = $schema['parameters'] ?? null;

        if ($name !== 'catalog.get_synthetic_good'
            || ! is_string($description) || $description === '' || mb_strlen($description) > 256
            || ! is_array($parameters)) {
            throw new PolicyViolation('timeweb_tool_schema_blocked', 'Only the code-owned synthetic catalog tool is allowed in Stage 05.');
        }

        return [
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
            'strict' => true,
        ];
    }

    private function assertBounded(array $payload): array
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($encoded) || strlen($encoded) > 65_536) {
            throw new PolicyViolation('timeweb_wire_payload_too_large', 'Timeweb wire payload exceeds its fixed byte cap.');
        }

        return $payload;
    }
}
