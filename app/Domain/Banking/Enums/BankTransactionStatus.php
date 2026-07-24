<?php

namespace App\Domain\Banking\Enums;

enum BankTransactionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
    case Unknown = 'unknown';

    public function isEffective(): bool
    {
        return $this === self::Posted;
    }
}
