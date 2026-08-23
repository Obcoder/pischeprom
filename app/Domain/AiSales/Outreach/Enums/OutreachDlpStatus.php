<?php

namespace App\Domain\AiSales\Outreach\Enums;

enum OutreachDlpStatus: string
{
    case Passed = 'passed';
    case Blocked = 'blocked';
}
