<?php

namespace App\Domain\Banking\DTO;

use Carbon\CarbonImmutable;

final readonly class BankBalanceData
{
    public function __construct(
        public string $type,
        public string $amount,
        public string $currency,
        public ?CarbonImmutable $statementDate,
        public CarbonImmutable $asOf,
        public string $source,
    ) {}
}
