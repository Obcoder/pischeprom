<?php

namespace App\Domain\Banking\DTO;

final readonly class BankImportResult
{
    public function __construct(
        public int $received,
        public int $created,
        public int $updated,
        public int $skipped,
        public array $transactionIds,
    ) {}
}
