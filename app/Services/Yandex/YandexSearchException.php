<?php

namespace App\Services\Yandex;

use RuntimeException;

class YandexSearchException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly string $safeCode,
        string $message = 'Yandex Search request failed safely.',
    ) {
        parent::__construct($message);
    }
}
