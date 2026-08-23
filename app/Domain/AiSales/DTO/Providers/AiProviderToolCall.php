<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class AiProviderToolCall
{
    public function __construct(
        public string $callId,
        public string $toolCode,
        public string $toolVersion,
        public array $arguments,
        public string $argumentsHash,
    ) {}
}
