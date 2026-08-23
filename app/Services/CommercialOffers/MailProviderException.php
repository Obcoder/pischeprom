<?php

namespace App\Services\CommercialOffers;

use RuntimeException;

final class MailProviderException extends RuntimeException
{
    public function __construct(
        public readonly MailProviderSafeErrorCode $safeCode,
        public readonly ?string $httpStatusCategory = null,
        public readonly ?string $safeRequestId = null,
        public readonly ?string $responseHash = null,
        public readonly ?string $safeDetailCode = null,
        public readonly bool $ambiguousAcceptance = false,
    ) {
        parent::__construct($safeCode->summary());
    }
}
