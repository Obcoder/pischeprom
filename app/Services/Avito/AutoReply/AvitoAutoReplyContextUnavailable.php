<?php

namespace App\Services\Avito\AutoReply;

use RuntimeException;

class AvitoAutoReplyContextUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reasonCode = 'conversation_context_too_large')
    {
        parent::__construct($reasonCode);
    }
}
