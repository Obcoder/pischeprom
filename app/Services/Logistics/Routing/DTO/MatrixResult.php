<?php

namespace App\Services\Logistics\Routing\DTO;

final readonly class MatrixResult
{
    /** @param list<MatrixCell> $cells */
    public function __construct(
        public array $cells,
        public string $provider,
        public ?string $routingEngineVersion,
        public ?string $osmDataVersion,
    ) {}

    public function cell(int $sourceIndex, int $targetIndex): ?MatrixCell
    {
        foreach ($this->cells as $cell) {
            if ($cell->sourceIndex === $sourceIndex && $cell->targetIndex === $targetIndex) {
                return $cell;
            }
        }

        return null;
    }
}
