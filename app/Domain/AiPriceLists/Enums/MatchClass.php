<?php

namespace App\Domain\AiPriceLists\Enums;

enum MatchClass: string
{
    case Exact = 'exact_match';
    case Probable = 'probable_match';
    case None = 'no_match';
    case Conflict = 'conflict';
    case Invalid = 'invalid_row';
    case Ignored = 'ignored';
}
