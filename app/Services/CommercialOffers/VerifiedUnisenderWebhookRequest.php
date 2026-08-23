<?php

namespace App\Services\CommercialOffers;

final class VerifiedUnisenderWebhookRequest
{
    public const REQUEST_ATTRIBUTE = 'verified_unisender_webhook';

    /**
     * @param  list<NormalizedUnisenderEvent>  $events
     */
    public function __construct(
        public readonly string $requestHash,
        public readonly array $events,
    ) {}
}
