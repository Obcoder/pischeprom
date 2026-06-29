<?php

namespace App\Services\Gis\Exceptions;

use RuntimeException;
use Throwable;

class GisException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
