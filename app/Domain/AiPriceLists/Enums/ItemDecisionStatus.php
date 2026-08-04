<?php

namespace App\Domain\AiPriceLists\Enums;

enum ItemDecisionStatus: string
{
    case Unreviewed = 'unreviewed';
    case Matched = 'matched';
    case CreateDraft = 'create_draft';
    case Ignored = 'ignored';
    case Invalid = 'invalid';
    case Applied = 'applied';
}
