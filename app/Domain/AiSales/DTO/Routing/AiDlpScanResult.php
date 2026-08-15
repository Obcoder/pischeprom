<?php

namespace App\Domain\AiSales\DTO\Routing;

final readonly class AiDlpScanResult
{
    public function __construct(
        public array $findings,
        public int $secretCount,
        public int $personalDataCount,
    ) {}

    public function blocked(): bool
    {
        return $this->secretCount > 0 || $this->personalDataCount > 0;
    }
}
