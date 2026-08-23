<?php

namespace App\Domain\AiSales\DTO\FindBuyers;

final readonly class FindBuyersProgressView
{
    public function __construct(private array $payload) {}

    public function toArray(): array
    {
        return $this->payload;
    }
}
