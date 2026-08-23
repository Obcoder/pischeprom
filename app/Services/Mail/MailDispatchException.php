<?php

namespace App\Services\Mail;

use RuntimeException;

class MailDispatchException extends RuntimeException
{
    public function __construct(
        public readonly string $safeCode,
        string $message = 'Письмо не отправлено.',
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
