<?php

namespace App\Domain\Banking\Exceptions;

class BankUnavailableException extends BankingException
{
    public function __construct(
        string $message = 'Sber API is temporarily unavailable.',
        ?int $status = 503,
        ?string $cause = null,
        ?string $endpoint = null,
    ) {
        parent::__construct($message, 'bank_unavailable', true, $status, $cause, $endpoint);
    }
}
