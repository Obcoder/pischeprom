<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderErrorCategory;
use RuntimeException;

class TimewebTransportException extends RuntimeException
{
    public function __construct(
        public readonly AiProviderErrorCategory $category,
        public readonly string $safeCode,
        public readonly bool $retryable = false,
        public readonly ?int $statusCode = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct('Timeweb AI Gateway request failed safely.');
    }
}
