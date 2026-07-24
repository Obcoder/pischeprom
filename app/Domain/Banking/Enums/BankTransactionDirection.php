<?php

namespace App\Domain\Banking\Enums;

enum BankTransactionDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
