<?php

namespace App\Domain\Banking\Exceptions;

class BankMalformedResponseException extends BankingException
{
    public function __construct(string $message = 'Sber API returned a malformed response.', ?string $endpoint = null)
    {
        parent::__construct($message, 'malformed_response', false, endpointAlias: $endpoint);
    }
}
