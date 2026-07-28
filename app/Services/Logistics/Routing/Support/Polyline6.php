<?php

namespace App\Services\Logistics\Routing\Support;

use InvalidArgumentException;

final class Polyline6
{
    /** @param list<string> $shapes */
    public static function combine(array $shapes): ?string
    {
        $points = [];

        foreach ($shapes as $shape) {
            $decoded = self::decode($shape);

            if ($points !== [] && $decoded !== [] && end($points) === $decoded[0]) {
                array_shift($decoded);
            }

            array_push($points, ...$decoded);
        }

        return $points === [] ? null : self::encode($points);
    }

    /** @return list<array{0: float, 1: float}> */
    public static function decode(string $encoded): array
    {
        $index = 0;
        $latitude = 0;
        $longitude = 0;
        $points = [];
        $length = strlen($encoded);

        while ($index < $length) {
            $latitude += self::decodeValue($encoded, $index);
            $longitude += self::decodeValue($encoded, $index);
            $points[] = [$latitude / 1_000_000, $longitude / 1_000_000];
        }

        return $points;
    }

    /** @param list<array{0: float, 1: float}> $points */
    public static function encode(array $points): string
    {
        $encoded = '';
        $previousLatitude = 0;
        $previousLongitude = 0;

        foreach ($points as $point) {
            if (count($point) !== 2) {
                throw new InvalidArgumentException('Invalid polyline point.');
            }

            $latitude = (int) round($point[0] * 1_000_000);
            $longitude = (int) round($point[1] * 1_000_000);
            $encoded .= self::encodeValue($latitude - $previousLatitude);
            $encoded .= self::encodeValue($longitude - $previousLongitude);
            $previousLatitude = $latitude;
            $previousLongitude = $longitude;
        }

        return $encoded;
    }

    private static function decodeValue(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($encoded);

        do {
            if ($index >= $length) {
                throw new InvalidArgumentException('Truncated polyline6 value.');
            }

            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        return $encoded.chr($value + 63);
    }
}
