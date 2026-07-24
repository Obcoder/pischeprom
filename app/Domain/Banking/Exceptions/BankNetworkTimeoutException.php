<?php

namespace App\Domain\Banking\Exceptions;

class BankNetworkTimeoutException extends BankingException
{
    public function __construct(string $message = 'Sber API request timed out.', ?string $endpoint = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'network_timeout', true, endpointAlias: $endpoint, previous: $previous);
    }
}
