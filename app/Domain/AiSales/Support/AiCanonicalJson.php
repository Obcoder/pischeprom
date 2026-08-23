<?php

namespace App\Domain\AiSales\Support;

use JsonException;

final class AiCanonicalJson
{
    /** @throws JsonException */
    public static function encode(array $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @throws JsonException */
    public static function hash(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
