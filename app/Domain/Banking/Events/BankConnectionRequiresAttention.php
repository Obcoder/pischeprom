<?php

namespace App\Domain\Banking\Events;

use App\Models\BankConnection;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankConnectionRequiresAttention implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public BankConnection $connection,
        public string $reason,
    ) {}
}
