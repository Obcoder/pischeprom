<?php

namespace App\Domain\AiPriceLists\Contracts;

use App\Domain\AiPriceLists\DTO\FileScanResult;

interface FileScannerInterface
{
    public function scan(string $localPath): FileScanResult;
}
