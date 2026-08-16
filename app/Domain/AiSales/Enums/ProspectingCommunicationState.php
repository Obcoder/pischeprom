<?php

namespace App\Domain\AiSales\Enums;

enum ProspectingCommunicationState: string
{
    case Unknown = 'unknown';
    case ReviewRequired = 'review_required';
    case DoNotContact = 'do_not_contact';
    case Suppressed = 'suppressed';

    public function contactEligible(): bool
    {
        return false;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
