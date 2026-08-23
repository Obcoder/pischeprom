<?php

namespace App\Domain\AiSales\Outreach;

use App\Models\OutreachDispatch;

final readonly class OutreachDispatchActionResult
{
    public function __construct(
        public OutreachDispatch $dispatch,
        public OutreachFinalRevalidationResult $revalidation,
        public bool $accepted,
    ) {}
}
