<?php

namespace App\Domain\AiSales\Web;

final readonly class ResolvedPublicUrl
{
    /** @param list<string> $ipAddresses */
    public function __construct(
        public string $url,
        public string $host,
        public string $registrableDomain,
        public array $ipAddresses,
    ) {}
}
