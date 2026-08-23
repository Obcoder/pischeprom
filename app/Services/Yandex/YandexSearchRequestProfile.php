<?php

namespace App\Services\Yandex;

final readonly class YandexSearchRequestProfile
{
    public function __construct(
        public string $code,
        public int $maxQueryCharacters,
        public int $maxResults,
        public int $maxPages,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public int $maxResponseBytes,
        public int $maxXmlBytes,
    ) {}
}
