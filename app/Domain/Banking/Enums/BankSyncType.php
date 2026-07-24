<?php

namespace App\Domain\Banking\Enums;

enum BankSyncType: string
{
    case Accounts = 'accounts';
    case Initial = 'initial';
    case Daily = 'daily';
    case Incremental = 'incremental';
    case Control = 'control';
    case Manual = 'manual';
}
