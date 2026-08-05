<?php

namespace App\Domain\Avito\Exceptions;

use RuntimeException;

class AvitoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category = 'avito_error',
        public readonly int $httpStatus = 422,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
