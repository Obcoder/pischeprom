<?php

namespace App\Domain\Banking\Exceptions;

class ReadOnlyViolationException extends BankingException
{
    public function __construct(string $message = 'The banking provider is read-only and rejected this operation.')
    {
        parent::__construct($message, 'read_only_violation', false, 405);
    }
}
