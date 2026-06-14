<?php

namespace App\Services\Yandex;

use RuntimeException;

class DirectApiAddException extends RuntimeException
{
    public function __construct(string $message, public readonly array $externalIds = [])
    {
        parent::__construct($message);
    }
}
