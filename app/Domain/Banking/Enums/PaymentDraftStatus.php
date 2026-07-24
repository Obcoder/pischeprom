<?php

namespace App\Domain\Banking\Enums;

enum PaymentDraftStatus: string
{
    case Draft = 'draft';
    case Exported = 'exported';
    case Cancelled = 'cancelled';
}
