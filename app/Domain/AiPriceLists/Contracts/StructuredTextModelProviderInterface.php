<?php

namespace App\Domain\AiPriceLists\Contracts;

use App\Domain\AiPriceLists\DTO\StructuredModelRequest;
use App\Domain\AiPriceLists\DTO\StructuredModelResponse;

interface StructuredTextModelProviderInterface
{
    public function configured(): bool;

    public function generate(StructuredModelRequest $request): StructuredModelResponse;
}
