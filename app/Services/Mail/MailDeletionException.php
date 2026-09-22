<?php

namespace App\Services\Mail;

use RuntimeException;

class MailDeletionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
