<?php

namespace App\Domain\AiSales\DTO\Providers;

use InvalidArgumentException;

final readonly class AiProviderInputItem
{
    public function __construct(
        public string $type,
        public string $label,
        public array $data,
    ) {
        if (! in_array($type, ['instruction', 'sanitized_data', 'assistant_tool_call', 'tool_result'], true)) {
            throw new InvalidArgumentException('Unsupported provider input item type.');
        }

        if ($label === '' || mb_strlen($label) > 96) {
            throw new InvalidArgumentException('Provider input labels must be bounded.');
        }

        self::assertBounded($data, 0);
    }

    private static function assertBounded(array $value, int $depth): void
    {
        if ($depth > 4 || count($value) > 100) {
            throw new InvalidArgumentException('Provider input structure exceeds its bounds.');
        }

        foreach ($value as $key => $item) {
            if (! is_int($key) && (! is_string($key) || mb_strlen($key) > 96)) {
                throw new InvalidArgumentException('Provider input keys must be bounded strings.');
            }

            if (is_array($item)) {
                self::assertBounded($item, $depth + 1);

                continue;
            }

            if (! is_scalar($item) && $item !== null) {
                throw new InvalidArgumentException('Provider input values must be scalar or bounded arrays.');
            }

            if (is_string($item) && strlen($item) > 24_576) {
                throw new InvalidArgumentException('Provider input strings exceed the byte cap.');
            }
        }
    }
}
