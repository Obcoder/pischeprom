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
        public readonly array $metadata = [],
    ) {
        parent::__construct($message);
    }
}
