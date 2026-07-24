<?php

namespace App\Domain\Banking\Events;

use App\Models\BankTransactionAllocation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAllocationReversed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public BankTransactionAllocation $allocation) {}
}
