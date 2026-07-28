<?php

namespace App\Services\Logistics\Routing\Exceptions;

use RuntimeException;
use Throwable;

class RoutingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $domainCode = 'routing_failed',
        public readonly bool $retryable = false,
        public readonly int $httpStatus = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
