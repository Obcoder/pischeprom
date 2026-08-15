<?php

namespace App\Domain\AiSales\Enums;

enum AiProviderResponseStatus: string
{
    case Completed = 'completed';
    case RequiresAction = 'requires_action';
    case Failed = 'failed';
}
