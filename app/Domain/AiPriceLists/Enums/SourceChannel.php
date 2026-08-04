<?php

namespace App\Domain\AiPriceLists\Enums;

enum SourceChannel: string
{
    case Email = 'email';
    case Max = 'max';

    public function label(): string
    {
        return $this === self::Email ? 'Почта' : 'MAX';
    }
}
