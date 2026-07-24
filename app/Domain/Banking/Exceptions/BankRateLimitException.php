<?php

namespace App\Domain\Banking\Exceptions;

class BankRateLimitException extends BankingException
{
    public function __construct(int $retryAfterSeconds, ?string $cause = null, ?string $endpoint = null)
    {
        parent::__construct(
            'Sber API rate limit exceeded.',
            'rate_limit',
            true,
            429,
            $cause,
            $endpoint,
            max(1, $retryAfterSeconds),
        );
    }
}
