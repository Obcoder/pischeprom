<?php

namespace App\Domain\AiPriceLists\Contracts;

use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\DTO\OcrResponse;

interface OcrProviderInterface
{
    public function configured(): bool;

    public function recognize(OcrRequest $request): OcrResponse;
}
