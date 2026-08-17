<?php

namespace App\Services\CommercialOffers;

final class UnisenderSendResult
{
    public readonly array $response;

    public function __construct(
        public readonly bool $successful,
        public readonly ?string $jobId = null,
        public readonly array $failedEmails = [],
        public readonly ?string $httpStatusCategory = null,
        public readonly ?string $safeRequestId = null,
        public readonly ?string $responseHash = null,
        public readonly UnisenderRequestProfile $requestProfile = UnisenderRequestProfile::LegacyManual,
        public readonly ?string $safeErrorCode = null,
        public readonly ?string $safeSummary = null,
        array $safeResponseMetadata = [],
    ) {
        $this->response = array_filter([
            'job_id' => $jobId,
            'http_status_category' => $httpStatusCategory,
            'safe_request_id' => $safeRequestId,
            'response_hash' => $responseHash,
            'failed_count' => count($failedEmails),
            'request_profile' => $requestProfile->value,
            'safe_error_code' => $safeErrorCode,
            'safe_summary' => $safeSummary,
        ] + $safeResponseMetadata, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'job_id' => $this->jobId,
            'response' => $this->response,
            'failed_count' => count($this->failedEmails),
            'error_code' => $this->safeErrorCode,
        ];
    }
}
