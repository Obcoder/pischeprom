<?php

namespace App\Domain\AiSales\Search;

use RuntimeException;

class SearchProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly string $safeCode,
        string $message = 'Search provider request failed safely.',
    ) {
        parent::__construct($message);
    }
}
