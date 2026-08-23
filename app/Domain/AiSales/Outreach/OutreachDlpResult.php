<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Support\AiCanonicalJson;

final readonly class OutreachDlpResult
{
    public function __construct(public bool $passed, public array $codes) {}

    public function hash(): string
    {
        return AiCanonicalJson::hash(['passed' => $this->passed, 'codes' => $this->codes, 'policy' => 'stage12-v1']);
    }
}
