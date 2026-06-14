<?php

namespace App\Services\Yandex;

use App\Models\Good;
use Illuminate\Support\Str;

class DirectCampaignNamingService
{
    public function campaign(Good $good, string $segment = 'B2B', ?array $regions = null): string
    {
        $region = filled($regions) ? 'REG' : 'NO_REG';

        return $this->limit("Pischeprom | {$this->productAlias($good)} | {$segment} | {$region}");
    }

    public function adGroup(Good $good, string $segment): string
    {
        return $this->limit("{$this->productAlias($good)} | {$segment}");
    }

    public function ad(Good $good, string $variant): string
    {
        return $this->limit("{$this->productAlias($good)} | {$variant}");
    }

    public function productAlias(Good $good): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $good->name)) ?: 'Good');

        return $this->limit($name, 96);
    }

    private function limit(string $value, int $limit = 255): string
    {
        return Str::limit(trim($value), $limit, '');
    }
}
