<?php

namespace App\Domain\Banking\Exceptions;

class BankConfigurationException extends BankingException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'configuration', false);
    }
}
