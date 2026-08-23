<?php

namespace App\Services\CommercialOffers;

final class NormalizedUnisenderEvent
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $eventType,
        public readonly string $status,
        public readonly ?string $providerEventId,
        public readonly ?string $providerJobId,
        public readonly ?string $providerMessageId,
        public readonly ?string $occurredAt,
        public readonly ?int $campaignRecipientId,
        public readonly ?int $contactId,
        public readonly ?int $mailingMessageId,
        public readonly ?int $sendingId,
        public readonly ?int $mailMessageId,
        public readonly ?string $recipientEmail,
    ) {}
}
