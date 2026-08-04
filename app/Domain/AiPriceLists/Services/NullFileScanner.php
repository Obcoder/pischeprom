<?php

namespace App\Domain\AiPriceLists\Services;

use App\Domain\AiPriceLists\Contracts\FileScannerInterface;
use App\Domain\AiPriceLists\DTO\FileScanResult;

class NullFileScanner implements FileScannerInterface
{
    public function scan(string $localPath): FileScanResult
    {
        return new FileScanResult(true, 'not_configured');
    }
}
