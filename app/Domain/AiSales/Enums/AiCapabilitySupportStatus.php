<?php

namespace App\Domain\AiSales\Enums;

enum AiCapabilitySupportStatus: string
{
    case Supported = 'supported';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    public function permitsUse(): bool
    {
        return $this === self::Supported;
    }
}
