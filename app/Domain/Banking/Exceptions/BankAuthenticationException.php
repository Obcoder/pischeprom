<?php

namespace App\Domain\Banking\Exceptions;

class BankAuthenticationException extends BankingException
{
    public function __construct(string $message = 'Bank authentication failed.', ?string $cause = null, ?string $endpoint = null)
    {
        parent::__construct($message, 'authentication', false, 401, $cause, $endpoint);
    }
}
