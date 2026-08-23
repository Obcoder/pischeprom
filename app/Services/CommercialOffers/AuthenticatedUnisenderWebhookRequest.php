<?php

namespace App\Services\CommercialOffers;

final class AuthenticatedUnisenderWebhookRequest
{
    public const REQUEST_ATTRIBUTE = 'authenticated_unisender_webhook';

    /**
     * The provider events exist only in request memory between signature
     * verification and allowlisted normalization.
     *
     * @param  list<array<string, mixed>>  $providerEvents
     */
    public function __construct(
        public readonly string $requestHash,
        private readonly array $providerEvents,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function providerEvents(): array
    {
        return $this->providerEvents;
    }
}
