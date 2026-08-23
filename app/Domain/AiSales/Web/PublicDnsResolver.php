<?php

namespace App\Domain\AiSales\Web;

class PublicDnsResolver
{
    /** @param array<string, list<string>> $staticRecords */
    public function __construct(private readonly array $staticRecords = []) {}

    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (array_key_exists($host, $this->staticRecords)) {
            return array_values(array_unique($this->staticRecords[$host]));
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return collect($records)
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
