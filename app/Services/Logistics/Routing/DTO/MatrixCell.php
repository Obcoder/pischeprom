<?php

namespace App\Services\Logistics\Routing\DTO;

final readonly class MatrixCell
{
    public function __construct(
        public int $sourceIndex,
        public int $targetIndex,
        public ?int $distanceM,
        public ?int $durationS,
    ) {}

    public function hasRoute(): bool
    {
        return $this->distanceM !== null && $this->durationS !== null;
    }
}
