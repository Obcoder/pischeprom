<?php

namespace App\Domain\AiPriceLists\Providers;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrRequest;
use App\Domain\AiPriceLists\DTO\OcrResponse;

class FakeOcrProvider implements OcrProviderInterface
{
    public function __construct(private array $rows = []) {}

    public function configured(): bool
    {
        return true;
    }

    public function recognize(OcrRequest $request): OcrResponse
    {
        return new OcrResponse($this->rows, 1, 'fake-ocr-request', 0, ['model' => 'fake']);
    }
}
