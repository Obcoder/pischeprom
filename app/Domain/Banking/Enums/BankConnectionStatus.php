<?php

namespace App\Domain\Banking\Enums;

enum BankConnectionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
    case Error = 'error';
    case ReauthorizationRequired = 'reauthorization_required';
}
