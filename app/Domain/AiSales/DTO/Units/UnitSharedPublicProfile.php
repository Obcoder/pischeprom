<?php

namespace App\Domain\AiSales\DTO\Units;

use App\Domain\AiSales\DTO\AbstractSafeAiDto;

final class UnitSharedPublicProfile extends AbstractSafeAiDto
{
    public function __construct(
        private readonly string $name,
        private readonly array $aliases = [],
        private readonly array $industries = [],
        private readonly array $cities = [],
        private readonly array $publicUris = [],
        private readonly array $observations = [],
    ) {}

    public function fields(): array
    {
        return [
            'name' => self::text($this->name, 255),
            'aliases' => self::stringList($this->aliases, 20, 255),
            'industries' => self::stringList($this->industries, 20, 255),
            'cities' => self::stringList($this->cities, 20, 255),
            'public_uris' => self::stringList($this->publicUris, 10, 1024),
            'observations' => self::stringList($this->observations, 25, 500),
        ];
    }

    public function maxBytes(): int
    {
        return 24_576;
    }
}
