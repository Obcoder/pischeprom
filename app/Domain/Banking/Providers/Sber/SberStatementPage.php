<?php

namespace App\Domain\Banking\Providers\Sber;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class SberStatementPage
{
    public function __construct(
        public Collection $transactions,
        public Collection $balances,
        public ?string $nextUrl,
        public ?CarbonImmutable $reloadTime,
    ) {}
}
