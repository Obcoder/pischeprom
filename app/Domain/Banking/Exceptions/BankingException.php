<?php

namespace App\Domain\Banking\Exceptions;

use RuntimeException;
use Throwable;

class BankingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category = 'banking',
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $bankCause = null,
        public readonly ?string $endpointAlias = null,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
