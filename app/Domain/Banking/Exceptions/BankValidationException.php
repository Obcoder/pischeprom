<?php

namespace App\Domain\Banking\Exceptions;

class BankValidationException extends BankingException
{
    public function __construct(
        string $message = 'Bank API rejected the request.',
        ?int $status = 400,
        ?string $cause = null,
        ?string $endpoint = null,
    ) {
        parent::__construct($message, 'validation', false, $status, $cause, $endpoint);
    }
}
