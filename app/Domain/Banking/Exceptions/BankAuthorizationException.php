<?php

namespace App\Domain\Banking\Exceptions;

class BankAuthorizationException extends BankingException
{
    public function __construct(string $message = 'Bank API access is forbidden.', ?string $cause = null, ?string $endpoint = null)
    {
        parent::__construct($message, 'authorization_scope', false, 403, $cause, $endpoint);
    }
}
