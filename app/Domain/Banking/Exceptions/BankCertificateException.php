<?php

namespace App\Domain\Banking\Exceptions;

class BankCertificateException extends BankingException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'certificate', false, previous: $previous);
    }
}
