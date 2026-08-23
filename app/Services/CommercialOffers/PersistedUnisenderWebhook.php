<?php

namespace App\Services\CommercialOffers;

final class PersistedUnisenderWebhook
{
    /**
     * @param  list<int>  $eventIdsToQueue
     */
    public function __construct(
        public readonly int $webhookCallId,
        public readonly array $eventIdsToQueue,
        public readonly bool $duplicateRequest,
        public readonly int $acceptedEventCount,
    ) {}
}
