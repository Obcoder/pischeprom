<?php

namespace App\Domain\Banking\Events;

use App\Models\BankTransaction;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankTransactionChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public BankTransaction $transaction,
        public array $changedFields,
    ) {}
}
