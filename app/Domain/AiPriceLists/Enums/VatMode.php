<?php

namespace App\Domain\AiPriceLists\Enums;

enum VatMode: string
{
    case Included = 'included';
    case Excluded = 'excluded';
    case Unknown = 'unknown';
}
