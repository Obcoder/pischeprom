<?php

namespace App\Services\Avito\AutoReply;

use App\Domain\Avito\Exceptions\AvitoException;

class AvitoAutoReplyCancelled extends AvitoException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct('Автоответ отменён до отправки в Avito.', 'auto_reply_cancelled');
    }
}
