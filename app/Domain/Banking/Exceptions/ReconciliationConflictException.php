<?php

namespace App\Domain\Banking\Exceptions;

class ReconciliationConflictException extends BankingException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'reconciliation_conflict', false, 409);
    }
}
