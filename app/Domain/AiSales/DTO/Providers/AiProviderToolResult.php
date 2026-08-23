<?php

namespace App\Domain\AiSales\DTO\Providers;

final readonly class AiProviderToolResult
{
    public function __construct(
        public string $callId,
        public string $status,
        public array $safeOutput,
        public string $outputHash,
    ) {}
}
