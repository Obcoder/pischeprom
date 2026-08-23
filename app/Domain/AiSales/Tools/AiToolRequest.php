<?php

namespace App\Domain\AiSales\Tools;

use InvalidArgumentException;

final readonly class AiToolRequest
{
    public function __construct(
        public string $toolCode,
        public string $toolVersion,
        public array $input,
        public string $callerIdempotencyKey,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.]{2,95}$/', $toolCode) !== 1) {
            throw new InvalidArgumentException('Tool code must be an explicit code-owned identifier.');
        }

        if (preg_match('/^[A-Za-z0-9._-]{1,32}$/', $toolVersion) !== 1) {
            throw new InvalidArgumentException('Tool version is invalid.');
        }

        if ($callerIdempotencyKey === '' || strlen($callerIdempotencyKey) > 128) {
            throw new InvalidArgumentException('Tool idempotency key must be bounded.');
        }
    }
}
