<?php

namespace App\Infrastructure\AiSales\Timeweb;

final readonly class TimewebGatewayResponse
{
    public function __construct(
        public int $statusCode,
        public array $data,
        public ?string $requestId,
        public int $responseBytes,
    ) {}
}
