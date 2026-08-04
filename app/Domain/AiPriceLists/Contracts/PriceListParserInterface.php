<?php

namespace App\Domain\AiPriceLists\Contracts;

use App\Domain\AiPriceLists\DTO\ExtractionResult;

interface PriceListParserInterface
{
    public function supports(string $extension): bool;

    public function parse(string $localPath, string $extension): ExtractionResult;
}
