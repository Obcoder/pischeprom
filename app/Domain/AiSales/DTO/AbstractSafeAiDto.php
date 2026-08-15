<?php

namespace App\Domain\AiSales\DTO;

use InvalidArgumentException;

abstract class AbstractSafeAiDto implements SafeAiDto
{
    protected static function text(mixed $value, int $maxCharacters): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException('Safe DTO text values must be scalar.');
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $normalized === '' ? null : mb_substr($normalized, 0, $maxCharacters);
    }

    protected static function stringList(array $values, int $maxRows, int $maxCharacters): array
    {
        return collect(array_slice($values, 0, $maxRows))
            ->map(fn (mixed $value) => self::text($value, $maxCharacters))
            ->filter(fn (?string $value) => $value !== null)
            ->values()
            ->all();
    }

    protected static function scalarMap(array $values, int $maxRows, int $maxCharacters): array
    {
        $result = [];

        foreach (array_slice($values, 0, $maxRows, true) as $key => $value) {
            $safeKey = self::text($key, 64);
            $safeValue = self::text($value, $maxCharacters);

            if ($safeKey !== null && $safeValue !== null) {
                $result[$safeKey] = $safeValue;
            }
        }

        return $result;
    }
}
