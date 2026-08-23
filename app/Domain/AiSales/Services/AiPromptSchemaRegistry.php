<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiAgentDefinition;

class AiPromptSchemaRegistry
{
    public function get(AiAgentDefinition $definition): array
    {
        $entry = config("ai-sales.prompt_registry.{$definition->code}");

        if (! is_array($entry)
            || ! is_string($entry['template'] ?? null)
            || ! is_array($entry['schema'] ?? null)
            || (string) ($entry['version'] ?? '') !== (string) $definition->prompt_version
            || (string) ($entry['version'] ?? '') !== (string) $definition->schema_version) {
            throw new PolicyViolation('prompt_schema_registry_mismatch', 'Agent prompt/schema is not present in the code-owned registry.');
        }

        $promptHash = hash('sha256', $entry['template']);
        $schemaHash = hash('sha256', json_encode($entry['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! hash_equals($definition->prompt_hash, $promptHash) || ! hash_equals($definition->schema_hash, $schemaHash)) {
            throw new PolicyViolation('prompt_schema_hash_mismatch', 'Agent definition hashes do not match the code-owned prompt/schema.');
        }

        return [
            'version' => (string) $entry['version'],
            'template' => $entry['template'],
            'schema' => $entry['schema'],
            'prompt_hash' => $promptHash,
            'schema_hash' => $schemaHash,
        ];
    }
}
