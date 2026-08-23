<?php

namespace App\Infrastructure\AiSales\Timeweb;

use JsonException;

class TimewebSyntheticSchemaValidator
{
    public function valid(mixed $text): bool
    {
        if (! is_string($text) || strlen($text) > 16_384) {
            return false;
        }

        try {
            $value = json_decode($text, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($value)) {
            return false;
        }

        $keys = array_keys($value);
        sort($keys);

        if ($keys !== ['category', 'confidence', 'keywords']) {
            return false;
        }

        return is_string($value['category'])
            && mb_strlen($value['category']) <= 128
            && (is_int($value['confidence']) || is_float($value['confidence']))
            && $value['confidence'] >= 0
            && $value['confidence'] <= 1
            && is_array($value['keywords'])
            && array_is_list($value['keywords'])
            && count($value['keywords']) <= 16
            && collect($value['keywords'])->every(
                static fn (mixed $keyword): bool => is_string($keyword) && mb_strlen($keyword) <= 128,
            );
    }
}
