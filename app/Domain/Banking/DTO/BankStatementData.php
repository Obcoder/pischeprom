<?php

namespace App\Domain\Banking\DTO;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class BankStatementData
{
    /**
     * @param  Collection<int, BankTransactionData>  $transactions
     * @param  Collection<int, BankBalanceData>  $balances
     */
    public function __construct(
        public Collection $transactions,
        public Collection $balances,
        public ?CarbonImmutable $cursor,
        public ?CarbonImmutable $reloadTime = null,
        public int $pages = 1,
    ) {}
}
