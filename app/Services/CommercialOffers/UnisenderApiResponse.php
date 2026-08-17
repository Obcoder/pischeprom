<?php

namespace App\Services\CommercialOffers;

final class UnisenderApiResponse
{
    public function __construct(
        public readonly array $payload,
        public readonly int $httpStatus,
        public readonly string $httpStatusCategory,
        public readonly string $responseHash,
        public readonly ?string $safeRequestId,
    ) {}

    public function safeMetadata(): array
    {
        return array_filter([
            'http_status_category' => $this->httpStatusCategory,
            'safe_request_id' => $this->safeRequestId,
            'response_hash' => $this->responseHash,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
