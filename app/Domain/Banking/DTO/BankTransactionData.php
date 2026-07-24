<?php

namespace App\Domain\Banking\DTO;

use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use Carbon\CarbonImmutable;

final readonly class BankTransactionData
{
    public function __construct(
        public ?string $operationId,
        public CarbonImmutable $operationDate,
        public ?CarbonImmutable $postingDate,
        public ?CarbonImmutable $valueDate,
        public BankTransactionDirection $direction,
        public string $amount,
        public string $currency,
        public BankTransactionStatus $status,
        public ?string $documentNumber,
        public ?string $purpose,
        public ?string $payerName,
        public ?string $payerInn,
        public ?string $payerKpp,
        public ?string $payerAccount,
        public ?string $payerBankName,
        public ?string $payerBic,
        public ?string $payerCorrAccount,
        public ?string $recipientName,
        public ?string $recipientInn,
        public ?string $recipientKpp,
        public ?string $recipientAccount,
        public ?string $recipientBankName,
        public ?string $recipientBic,
        public ?string $recipientCorrAccount,
        public ?CarbonImmutable $bankModifiedAt,
        public array $rawPayload,
    ) {}
}
