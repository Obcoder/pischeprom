<?php

namespace App\Services\Products;

use RuntimeException;

class ProductTranslationAiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
