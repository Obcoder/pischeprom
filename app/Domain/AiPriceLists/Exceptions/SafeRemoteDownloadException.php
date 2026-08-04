<?php

namespace App\Domain\AiPriceLists\Exceptions;

use RuntimeException;

class SafeRemoteDownloadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
