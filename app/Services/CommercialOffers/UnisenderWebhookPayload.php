<?php

namespace App\Services\CommercialOffers;

final class UnisenderWebhookPayload
{
    public function __construct(
        public readonly array $payload,
        public readonly array $events,
    ) {}
}
