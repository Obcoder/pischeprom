<?php

namespace App\Domain\Banking\Services;

use InvalidArgumentException;

final class DecimalMoney
{
    public static function normalize(mixed $amount): string
    {
        self::assertSafeInput($amount);
        $value = trim((string) $amount);
        $value = str_replace([' ', ','], ['', '.'], $value);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Money must be an integer or a decimal string with at most two fraction digits.');
        }

        $minor = self::toMinor($value);

        return self::fromMinor($minor);
    }

    public static function add(mixed $left, mixed $right): string
    {
        return self::fromMinor(self::toMinor($left) + self::toMinor($right));
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return self::fromMinor(self::toMinor($left) - self::toMinor($right));
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return self::toMinor($left) <=> self::toMinor($right);
    }

    public static function min(mixed $left, mixed $right): string
    {
        return self::compare($left, $right) <= 0 ? self::normalize($left) : self::normalize($right);
    }

    public static function max(mixed $left, mixed $right): string
    {
        return self::compare($left, $right) >= 0 ? self::normalize($left) : self::normalize($right);
    }

    public static function isPositive(mixed $amount): bool
    {
        return self::compare($amount, '0.00') > 0;
    }

    public static function toMinor(mixed $amount): int
    {
        self::assertSafeInput($amount);
        $value = trim((string) $amount);
        $value = str_replace([' ', ','], ['', '.'], $value);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid money value.');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[3] ?? '', 2, '0');
        $minorString = $whole.$fraction;

        if (strlen($minorString) > 18) {
            throw new InvalidArgumentException('Money value exceeds the supported range.');
        }

        $minor = (int) $minorString;

        return ($matches[1] ?? '') === '-' ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return sprintf('%s%d.%02d', $sign, $whole, $fraction);
    }

    private static function assertSafeInput(mixed $amount): void
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money must be passed as a decimal string or integer, never as float.');
        }
    }
}
