<?php

namespace App\Domain\AiPriceLists\Exceptions;

use RuntimeException;

class ExternalAiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        public readonly string $errorCode,
        public readonly ?string $externalRequestId = null,
    ) {
        parent::__construct($message);
    }
}
