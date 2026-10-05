<?php

namespace App\Services\Goods;

use RuntimeException;

class GoodTradeCodesAiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
