<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Support\AiCanonicalJson;

class AiToolSchemaValidator
{
    public function assertValid(array $schema, mixed $value, string $scope): void
    {
        $this->validate($schema, $value, '$', $scope, 0);
    }

    public function hash(array $schema): string
    {
        return AiCanonicalJson::hash($schema);
    }

    private function validate(array $schema, mixed $value, string $path, string $scope, int $depth): void
    {
        if ($depth > 8) {
            $this->fail($scope, 'depth_exceeded');
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $this->fail($scope, 'const_mismatch');
        }

        if (isset($schema['enum']) && (! is_array($schema['enum']) || ! in_array($value, $schema['enum'], true))) {
            $this->fail($scope, 'enum_mismatch');
        }

        $types = (array) ($schema['type'] ?? []);

        if ($types === [] || ! collect($types)->contains(fn (mixed $type): bool => $this->matchesType($type, $value))) {
            $this->fail($scope, 'type_mismatch');
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
                $this->fail($scope, 'string_too_short');
            }
            if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
                $this->fail($scope, 'string_too_long');
            }
            if (isset($schema['pattern']) && preg_match((string) $schema['pattern'], $value) !== 1) {
                $this->fail($scope, 'pattern_mismatch');
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $this->fail($scope, 'number_too_small');
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $this->fail($scope, 'number_too_large');
            }
        }

        if (! is_array($value)) {
            return;
        }

        if (($schema['type'] ?? null) === 'array' || (is_array($schema['type'] ?? null) && in_array('array', $schema['type'], true))) {
            if (! array_is_list($value)) {
                $this->fail($scope, 'array_expected');
            }
            if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
                $this->fail($scope, 'too_few_items');
            }
            if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
                $this->fail($scope, 'too_many_items');
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $this->validate($schema['items'], $item, $path.'.'.$index, $scope, $depth + 1);
                }
            }

            return;
        }

        if (array_is_list($value) && $value !== []) {
            $this->fail($scope, 'object_expected');
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach ((array) ($schema['required'] ?? []) as $required) {
            if (! is_string($required) || ! array_key_exists($required, $value)) {
                $this->fail($scope, 'required_field_missing');
            }
        }

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                $this->fail($scope, 'object_key_invalid');
            }

            if (! array_key_exists($key, $properties)) {
                $additional = $schema['additionalProperties'] ?? true;

                if ($additional === false) {
                    throw new PolicyViolation(
                        $scope.'_unknown_field',
                        'An input or output field is not present in the code-owned schema.',
                    );
                }

                if (is_array($additional)) {
                    $this->validate($additional, $item, $path.'.'.$key, $scope, $depth + 1);
                }

                continue;
            }

            $this->validate((array) $properties[$key], $item, $path.'.'.$key, $scope, $depth + 1);
        }
    }

    private function matchesType(mixed $type, mixed $value): bool
    {
        return match ($type) {
            'object' => is_array($value) && (! array_is_list($value) || $value === []),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }

    private function fail(string $scope, string $reason): never
    {
        throw new PolicyViolation($scope.'_schema_invalid', "Code-owned schema validation failed: {$reason}.");
    }
}
