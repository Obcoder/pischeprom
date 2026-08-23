<?php

namespace App\Domain\AiSales\Web;

use App\Domain\AiSales\Search\SearchProviderException;

class RegistrableDomainResolver
{
    private const TWO_LEVEL_SUFFIXES = [
        'co.uk', 'org.uk', 'com.au', 'com.br', 'com.cn', 'co.jp', 'co.in', 'com.tr',
    ];

    public function resolve(string $host): string
    {
        $host = mb_strtolower(rtrim(trim($host), '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new SearchProviderException('url_policy', 'registrable_domain_unavailable');
        }
        $labels = array_values(array_filter(explode('.', $host)));
        if (count($labels) < 2) {
            throw new SearchProviderException('url_policy', 'registrable_domain_unavailable');
        }
        $lastTwo = implode('.', array_slice($labels, -2));
        if (in_array($lastTwo, self::TWO_LEVEL_SUFFIXES, true) && count($labels) >= 3) {
            return implode('.', array_slice($labels, -3));
        }

        return $lastTwo;
    }
}
