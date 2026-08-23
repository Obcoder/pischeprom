<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class AiProviderCitation
{
    public function __construct(
        public string $reference,
        public ?string $label = null,
    ) {}
}
