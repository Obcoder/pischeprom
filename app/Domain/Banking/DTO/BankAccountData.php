<?php

namespace App\Domain\Banking\DTO;

final readonly class BankAccountData
{
    public function __construct(
        public ?string $externalId,
        public string $accountNumber,
        public string $maskedNumber,
        public ?string $name,
        public ?string $type,
        public string $currency,
        public string $status,
        public ?BankBalanceData $balance,
        public array $requisites,
        public array $rawPayload,
    ) {}
}
