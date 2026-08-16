<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\DTO\SafeAiDto;
use InvalidArgumentException;

final readonly class AiToolHandlerResult
{
    /** @param list<SafeAiDto> $items */
    public function __construct(
        public array $items,
        public string $sourceType,
        public ?int $sourceId = null,
    ) {
        if ($sourceType === '' || mb_strlen($sourceType) > 96) {
            throw new InvalidArgumentException('Tool source type must be a bounded code.');
        }

        foreach ($items as $item) {
            if (! $item instanceof SafeAiDto) {
                throw new InvalidArgumentException('Tool handlers may return only Safe DTO instances.');
            }
        }
    }
}
