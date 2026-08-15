<?php

namespace App\Domain\AiSales\DTO;

interface SafeAiDto
{
    /**
     * Return only explicitly declared, scalar or bounded-array fields.
     * Eloquent models and lazy relations are not accepted by this contract.
     */
    public function fields(): array;

    public function maxBytes(): int;
}
