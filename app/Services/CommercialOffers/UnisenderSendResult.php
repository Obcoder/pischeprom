<?php

namespace App\Services\CommercialOffers;

final class UnisenderSendResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $jobId = null,
        public readonly array $response = [],
        public readonly array $failedEmails = [],
        public readonly ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'job_id' => $this->jobId,
            'response' => $this->response,
            'failed_emails' => $this->failedEmails,
            'error' => $this->error,
        ];
    }
}
