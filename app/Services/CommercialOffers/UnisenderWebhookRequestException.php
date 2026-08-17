<?php

namespace App\Services\CommercialOffers;

use RuntimeException;

final class UnisenderWebhookRequestException extends RuntimeException
{
    public function __construct(
        public readonly MailProviderSafeErrorCode $safeCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($safeCode->summary());
    }
}
