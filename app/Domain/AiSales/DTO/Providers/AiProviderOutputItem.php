<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class AiProviderOutputItem
{
    public function __construct(
        public string $type,
        public array $data,
    ) {}
}
