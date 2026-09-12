<?php

namespace App\Services\Seo;

use RuntimeException;

class GoodSeoAiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
