<?php

namespace App\Domain\AiSales\Exceptions;

use RuntimeException;

class PolicyViolation extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message);
    }
}
