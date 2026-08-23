<?php

namespace App\Services\Yandex;

use InvalidArgumentException;

class YandexSearchProfileRegistry
{
    public const PRODUCT_PAGE = 'product_page_search';

    public const PROSPECTING = 'prospecting_b2b_discovery';

    public function get(string $code): YandexSearchRequestProfile
    {
        return match ($code) {
            self::PRODUCT_PAGE => new YandexSearchRequestProfile(
                self::PRODUCT_PAGE, 255, 100, 10, 5, 30, 1_048_576, 2_097_152,
            ),
            self::PROSPECTING => new YandexSearchRequestProfile(
                self::PROSPECTING, 512, 50, 5, 5, 15, 1_048_576, 2_097_152,
            ),
            default => throw new InvalidArgumentException('Unknown server-owned Yandex Search profile.'),
        };
    }
}
