<?php

namespace App\Domain\Banking\Enums;

enum BankEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';
}
